<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { destroy, store, update, analyzeReceipt } from '@/actions/App/Http/Controllers/ExpenseController';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Alert, AlertDescription } from '@/components/ui/alert';
import type { BreadcrumbItem } from '@/types';
import { ref, computed } from 'vue';
import { Loader2, Upload, FileText, Trash2, Edit, Receipt } from 'lucide-vue-next';

interface Expense {
    id: number;
    amount: string;
    description: string | null;
    category: string | null;
    date: string;
    receipt_path: string | null;
    created_at: string;
}

interface Summary {
    total: string;
    count: number;
    this_month: string;
}

interface Props {
    expenses: Expense[];
    summary: Summary;
}

defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Expenses',
        href: '/expenses',
    },
];

const isAddExpenseOpen = ref(false);
const isEditExpenseOpen = ref(false);
const isAnalyzing = ref(false);
const receiptFile = ref<File | null>(null);
const receiptPreview = ref<string | null>(null);
const analysisError = ref<string | null>(null);
const editingExpense = ref<Expense | null>(null);

const formData = ref({
    amount: '',
    description: '',
    category: '',
    date: new Date().toISOString().split('T')[0],
    receipt: null as File | null,
});

const handleFileSelect = (event: Event) => {
    const target = event.target as HTMLInputElement;
    const file = target.files?.[0];

    if (file) {
        receiptFile.value = file;

        // Only show preview for images, not PDFs
        if (file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = (e) => {
                receiptPreview.value = e.target?.result as string;
            };
            reader.readAsDataURL(file);
        } else {
            receiptPreview.value = null;
        }
    }
};

const handleAnalyzeReceipt = async () => {
    if (!receiptFile.value) return;

    isAnalyzing.value = true;
    analysisError.value = null;

    try {
        const formDataToSend = new FormData();
        formDataToSend.append('receipt', receiptFile.value);

        const response = await fetch('/expenses/analyze-receipt', {
            method: 'POST',
            body: formDataToSend,
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
        });

        const result = await response.json();

        if (result.success) {
            formData.value = {
                amount: result.data.amount.toString(),
                description: result.data.description,
                category: result.data.category,
                date: result.data.date,
                receipt: receiptFile.value,
            };
        } else {
            analysisError.value = result.message || 'Failed to analyze receipt';
        }
    } catch {
        analysisError.value = 'An error occurred while analyzing the receipt';
    } finally {
        isAnalyzing.value = false;
    }
};

const handleSubmit = () => {
    const submitData = new FormData();
    submitData.append('amount', formData.value.amount);
    submitData.append('description', formData.value.description || '');
    submitData.append('category', formData.value.category || '');
    submitData.append('date', formData.value.date);

    if (formData.value.receipt) {
        submitData.append('receipt', formData.value.receipt);
    }

    router.post(store(), submitData, {
        onSuccess: () => {
            resetForm();
            isAddExpenseOpen.value = false;
        },
    });
};

const handleUpdate = () => {
    if (!editingExpense.value) return;

    const submitData = new FormData();
    submitData.append('amount', formData.value.amount);
    submitData.append('description', formData.value.description || '');
    submitData.append('category', formData.value.category || '');
    submitData.append('date', formData.value.date);
    submitData.append('_method', 'PUT');

    if (formData.value.receipt) {
        submitData.append('receipt', formData.value.receipt);
    }

    router.post(update(editingExpense.value.id), submitData, {
        onSuccess: () => {
            resetForm();
            isEditExpenseOpen.value = false;
            editingExpense.value = null;
        },
    });
};

const handleDelete = (id: number) => {
    if (confirm('Are you sure you want to delete this expense?')) {
        router.delete(destroy(id));
    }
};

const openEditDialog = (expense: Expense) => {
    editingExpense.value = expense;
    formData.value = {
        amount: expense.amount,
        description: expense.description || '',
        category: expense.category || '',
        date: expense.date,
        receipt: null,
    };
    receiptFile.value = null;
    receiptPreview.value = null;
    isEditExpenseOpen.value = true;
};

const resetForm = () => {
    formData.value = {
        amount: '',
        description: '',
        category: '',
        date: new Date().toISOString().split('T')[0],
        receipt: null,
    };
    receiptFile.value = null;
    receiptPreview.value = null;
    analysisError.value = null;
};

const formatCurrency = (amount: string) => {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
    }).format(parseFloat(amount));
};

const getCategoryColor = (category: string | null) => {
    const colors: Record<string, string> = {
        'Food': 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200',
        'Transportation': 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
        'Shopping': 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200',
        'Utilities': 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
        'Entertainment': 'bg-pink-100 text-pink-800 dark:bg-pink-900 dark:text-pink-200',
        'Healthcare': 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
    };
    return colors[category || ''] || 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200';
};
</script>

<template>
    <Head title="Expenses" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold tracking-tight">Expenses</h1>
                    <p class="text-muted-foreground">
                        Track your expenses with AI-powered receipt scanning
                    </p>
                </div>
                <Dialog v-model:open="isAddExpenseOpen">
                    <DialogTrigger as-child>
                        <Button @click="resetForm">
                            <Upload class="mr-2 size-4" />
                            Add Expense
                        </Button>
                    </DialogTrigger>
                    <DialogContent class="max-w-2xl max-h-[90vh] overflow-y-auto">
                        <DialogHeader>
                            <DialogTitle>Add New Expense</DialogTitle>
                            <DialogDescription>
                                Upload a receipt to automatically extract expense details using AI
                            </DialogDescription>
                        </DialogHeader>

                        <div class="grid gap-4 py-4">
                            <div v-if="analysisError" class="mb-4">
                                <Alert variant="destructive">
                                    <AlertDescription>{{ analysisError }}</AlertDescription>
                                </Alert>
                            </div>

                            <div class="grid gap-2">
                                <Label>Receipt Image or PDF (Optional)</Label>
                                <div class="flex flex-col gap-2">
                                    <Input
                                        type="file"
                                        accept="image/*,application/pdf"
                                        @change="handleFileSelect"
                                    />
                                    <Button
                                        v-if="receiptFile"
                                        type="button"
                                        variant="secondary"
                                        @click="handleAnalyzeReceipt"
                                        :disabled="isAnalyzing"
                                    >
                                        <Loader2 v-if="isAnalyzing" class="mr-2 size-4 animate-spin" />
                                        <Receipt v-else class="mr-2 size-4" />
                                        {{ isAnalyzing ? 'Analyzing...' : 'Analyze with AI' }}
                                    </Button>
                                </div>
                                <div v-if="receiptFile" class="mt-2">
                                    <img v-if="receiptPreview" :src="receiptPreview" alt="Receipt preview" class="max-h-48 rounded border" />
                                    <div v-else class="flex items-center gap-2 p-3 rounded border bg-muted">
                                        <FileText class="size-5" />
                                        <span class="text-sm">{{ receiptFile.name }}</span>
                                    </div>
                                </div>
                            </div>

                            <div class="grid gap-2">
                                <Label for="amount">Amount *</Label>
                                <Input
                                    id="amount"
                                    v-model="formData.amount"
                                    type="number"
                                    step="0.01"
                                    placeholder="0.00"
                                    required
                                />
                            </div>

                            <div class="grid gap-2">
                                <Label for="description">Description</Label>
                                <Input
                                    id="description"
                                    v-model="formData.description"
                                    placeholder="What was this expense for?"
                                />
                            </div>

                            <div class="grid gap-2">
                                <Label for="category">Category</Label>
                                <Input
                                    id="category"
                                    v-model="formData.category"
                                    placeholder="e.g., Food, Transportation, Shopping"
                                />
                            </div>

                            <div class="grid gap-2">
                                <Label for="date">Date *</Label>
                                <Input
                                    id="date"
                                    v-model="formData.date"
                                    type="date"
                                    required
                                />
                            </div>
                        </div>

                        <DialogFooter>
                            <Button type="button" variant="outline" @click="isAddExpenseOpen = false">
                                Cancel
                            </Button>
                            <Button type="button" @click="handleSubmit">
                                Save Expense
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </div>

            <div class="grid gap-4 md:grid-cols-3">
                <Card>
                    <CardHeader>
                        <CardTitle>Total Expenses</CardTitle>
                        <CardDescription>All time</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">
                            {{ formatCurrency(summary.total) }}
                        </div>
                        <p class="text-sm text-muted-foreground">
                            {{ summary.count }} {{ summary.count === 1 ? 'expense' : 'expenses' }}
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>This Month</CardTitle>
                        <CardDescription>Current month spending</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">
                            {{ formatCurrency(summary.this_month) }}
                        </div>
                    </CardContent>
                </Card>
            </div>

            <div v-if="expenses.length === 0" class="text-center py-12">
                <FileText class="mx-auto size-12 text-muted-foreground mb-4" />
                <p class="text-muted-foreground">No expenses recorded yet.</p>
                <Button class="mt-4" @click="isAddExpenseOpen = true; resetForm()">
                    Add your first expense
                </Button>
            </div>

            <div v-else class="grid gap-4">
                <Card
                    v-for="expense in expenses"
                    :key="expense.id"
                >
                    <CardHeader>
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <div class="flex items-center gap-2">
                                    <CardTitle>{{ formatCurrency(expense.amount) }}</CardTitle>
                                    <Badge v-if="expense.category" :class="getCategoryColor(expense.category)">
                                        {{ expense.category }}
                                    </Badge>
                                </div>
                                <CardDescription v-if="expense.description">
                                    {{ expense.description }}
                                </CardDescription>
                                <div class="mt-1 text-sm text-muted-foreground">
                                    {{ expense.date }}
                                </div>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div class="flex gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                @click="openEditDialog(expense)"
                            >
                                <Edit class="mr-2 size-4" />
                                Edit
                            </Button>
                            <Button
                                variant="destructive"
                                size="sm"
                                @click="handleDelete(expense.id)"
                            >
                                <Trash2 class="mr-2 size-4" />
                                Delete
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>

        <!-- Edit Dialog -->
        <Dialog v-model:open="isEditExpenseOpen">
            <DialogContent class="max-w-2xl max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>Edit Expense</DialogTitle>
                    <DialogDescription>
                        Update expense details
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-4 py-4">
                    <div class="grid gap-2">
                        <Label for="edit-amount">Amount *</Label>
                        <Input
                            id="edit-amount"
                            v-model="formData.amount"
                            type="number"
                            step="0.01"
                            placeholder="0.00"
                            required
                        />
                    </div>

                    <div class="grid gap-2">
                        <Label for="edit-description">Description</Label>
                        <Input
                            id="edit-description"
                            v-model="formData.description"
                            placeholder="What was this expense for?"
                        />
                    </div>

                    <div class="grid gap-2">
                        <Label for="edit-category">Category</Label>
                        <Input
                            id="edit-category"
                            v-model="formData.category"
                            placeholder="e.g., Food, Transportation, Shopping"
                        />
                    </div>

                    <div class="grid gap-2">
                        <Label for="edit-date">Date *</Label>
                        <Input
                            id="edit-date"
                            v-model="formData.date"
                            type="date"
                            required
                        />
                    </div>
                </div>

                <DialogFooter>
                    <Button type="button" variant="outline" @click="isEditExpenseOpen = false">
                        Cancel
                    </Button>
                    <Button type="button" @click="handleUpdate">
                        Update Expense
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
