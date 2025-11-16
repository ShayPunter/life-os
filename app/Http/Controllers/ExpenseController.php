<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreExpenseRequest;
use App\Http\Requests\UpdateExpenseRequest;
use App\Models\Expense;
use App\Services\CurrencyConversionService;
use App\Services\GroqService;
use App\Services\ImageCompressionService;
use App\Services\PdfToImageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ExpenseController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(
        protected GroqService $groqService,
        protected ImageCompressionService $compressionService,
        protected CurrencyConversionService $currencyService,
        protected PdfToImageService $pdfToImageService,
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        $expenses = $request->user()
            ->expenses()
            ->latest('date')
            ->latest('created_at')
            ->get()
            ->map(fn (Expense $expense) => [
                'id' => $expense->id,
                'amount' => $expense->amount,
                'description' => $expense->description,
                'category' => $expense->category,
                'date' => $expense->date->format('Y-m-d'),
                'receipt_path' => $expense->receipt_path,
                'created_at' => $expense->created_at->format('Y-m-d'),
            ]);

        $summary = [
            'total' => $request->user()
                ->expenses()
                ->sum('amount'),
            'count' => $request->user()
                ->expenses()
                ->count(),
            'this_month' => $request->user()
                ->expenses()
                ->whereMonth('date', now()->month)
                ->whereYear('date', now()->year)
                ->sum('amount'),
        ];

        return Inertia::render('expenses/Index', [
            'expenses' => $expenses,
            'summary' => $summary,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreExpenseRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['user_id'] = $request->user()->id;

        if ($request->hasFile('receipt')) {
            $receiptPath = $this->processAndUploadReceipt($request->file('receipt'));
            $data['receipt_path'] = $receiptPath;
        }

        $request->user()->expenses()->create($data);

        return to_route('expenses.index')->with('status', 'Expense created successfully.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateExpenseRequest $request, Expense $expense): RedirectResponse
    {
        $this->authorize('update', $expense);

        $data = $request->validated();

        if ($request->hasFile('receipt')) {
            // Delete old receipt if it exists
            if ($expense->receipt_path) {
                $this->deleteReceiptFromStorage($expense->receipt_path);
            }

            $receiptPath = $this->processAndUploadReceipt($request->file('receipt'));
            $data['receipt_path'] = $receiptPath;
        }

        $expense->update($data);

        return to_route('expenses.index')->with('status', 'Expense updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Expense $expense): RedirectResponse
    {
        $this->authorize('delete', $expense);

        // Delete receipt file if it exists
        if ($expense->receipt_path) {
            $this->deleteReceiptFromStorage($expense->receipt_path);
        }

        $expense->delete();

        return to_route('expenses.index')->with('status', 'Expense deleted successfully.');
    }

    /**
     * Analyze a receipt using Groq AI.
     */
    public function analyzeReceipt(Request $request): JsonResponse
    {
        $request->validate([
            'receipt' => ['required', 'file', 'mimes:jpeg,jpg,png,webp,pdf', 'max:5120'],
        ]);

        try {
            $file = $request->file('receipt');
            $isPdf = $this->isPdf($file);

            // Store temporarily locally
            $tempUploadPath = $file->store('temp', 'local');
            $tempUploadFullPath = Storage::disk('local')->path($tempUploadPath);

            // Convert PDF to image if needed
            if ($isPdf) {
                $pdfConvertedPath = storage_path('app/temp/converted_'.pathinfo($tempUploadPath, PATHINFO_FILENAME).'.jpg');

                if (! $this->pdfToImageService->convertToJpeg($tempUploadFullPath, $pdfConvertedPath)) {
                    throw new \Exception('PDF conversion not available. Please upload an image file (JPEG, PNG, WebP) or install Ghostscript/Imagick for PDF support.');
                }

                // Use the converted image path
                $tempUploadFullPath = $pdfConvertedPath;
            }

            // Compress the image (whether original or converted from PDF)
            $tempCompressedPath = storage_path('app/temp/compressed_'.basename($tempUploadFullPath));
            $this->compressionService->compress($tempUploadFullPath, $tempCompressedPath);

            // Analyze directly from the local compressed file
            $result = $this->groqService->analyzeReceipt($tempCompressedPath);

            // Convert currency to EUR
            $converted = $this->currencyService->convertToEur(
                $result['amount'],
                $result['currency']
            );

            // Clean up temp files
            if (file_exists($tempCompressedPath)) {
                unlink($tempCompressedPath);
            }
            if ($isPdf && isset($pdfConvertedPath) && file_exists($pdfConvertedPath)) {
                unlink($pdfConvertedPath);
            }
            Storage::disk('local')->delete($tempUploadPath);

            return response()->json([
                'success' => true,
                'data' => [
                    'amount' => $converted['amount_eur'],
                    'original_amount' => $converted['original_amount'],
                    'original_currency' => $converted['original_currency'],
                    'exchange_rate' => $converted['exchange_rate'],
                    'description' => $result['description'],
                    'category' => $result['category'],
                    'date' => now()->format('Y-m-d'),
                ],
            ]);
        } catch (\Exception $e) {
            // Clean up any temp files
            if (isset($tempUploadPath)) {
                Storage::disk('local')->delete($tempUploadPath);
            }
            if (isset($tempCompressedPath) && file_exists($tempCompressedPath)) {
                unlink($tempCompressedPath);
            }
            if (isset($pdfConvertedPath) && file_exists($pdfConvertedPath)) {
                unlink($pdfConvertedPath);
            }

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Process and upload a receipt file.
     */
    protected function processAndUploadReceipt(\Illuminate\Http\UploadedFile $file): string
    {
        $isPdf = $this->isPdf($file);

        // Store temporarily
        $tempPath = $file->store('temp', 'local');
        $tempFullPath = Storage::disk('local')->path($tempPath);

        if ($isPdf) {
            // Upload PDF directly without compression
            $s3Path = $this->uploadToS3($tempFullPath, $file->getClientOriginalExtension());
        } else {
            // Compress the image
            $tempCompressedPath = storage_path('app/temp/compressed_'.basename($tempPath));
            $this->compressionService->compress($tempFullPath, $tempCompressedPath);

            // Upload to S3
            $s3Path = $this->uploadToS3($tempCompressedPath, $file->getClientOriginalExtension());

            // Clean up compressed file
            if (file_exists($tempCompressedPath)) {
                unlink($tempCompressedPath);
            }
        }

        // Clean up temp file
        Storage::disk('local')->delete($tempPath);

        return $s3Path;
    }

    /**
     * Upload a file to S3.
     */
    protected function uploadToS3(string $localPath, string $extension): string
    {
        $filename = 'receipts/'.Str::uuid().'.'.$extension;
        $fileContents = file_get_contents($localPath);

        Storage::disk($this->getStorageDisk())->put($filename, $fileContents, 'public');

        return $filename;
    }

    /**
     * Delete a receipt from storage.
     */
    protected function deleteReceiptFromStorage(string $path): void
    {
        Storage::disk($this->getStorageDisk())->delete($path);
    }

    /**
     * Get the storage disk to use for receipts.
     */
    protected function getStorageDisk(): string
    {
        return config('filesystems.default') === 's3' ? 's3' : 'public';
    }

    /**
     * Check if the uploaded file is a PDF.
     */
    protected function isPdf(\Illuminate\Http\UploadedFile $file): bool
    {
        return $file->getMimeType() === 'application/pdf';
    }
}
