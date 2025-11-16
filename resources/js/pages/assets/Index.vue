<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { destroy, store, update, incrementUses, decrementUses, incrementHours, decrementHours } from '@/actions/App/Http/Controllers/AssetController';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import type { BreadcrumbItem } from '@/types';
import { ref } from 'vue';
import { Package, Trash2, Edit, Plus, Minus, Clock } from 'lucide-vue-next';

interface Asset {
    id: number;
    name: string;
    description: string | null;
    cost: string;
    uses: number;
    hours: string;
    tracking_type: 'uses' | 'hours';
    cost_per_use: number | null;
    cost_per_hour: number | null;
    purchased_at: string;
    created_at: string;
}

interface Summary {
    total_cost: string;
    count: number;
    total_uses: number;
    total_hours: string;
}

interface Props {
    assets: Asset[];
    summary: Summary;
}

defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Assets',
        href: '/assets',
    },
];

const isAddAssetOpen = ref(false);
const isEditAssetOpen = ref(false);
const editingAsset = ref<Asset | null>(null);

const formData = ref({
    name: '',
    description: '',
    cost: '',
    original_cost: '',
    original_currency: 'EUR',
    tracking_type: 'uses' as 'uses' | 'hours',
    purchased_at: new Date().toISOString().split('T')[0],
});

const handleSubmit = () => {
    const submitData: Record<string, string> = {
        name: formData.value.name,
        description: formData.value.description || '',
        tracking_type: formData.value.tracking_type,
        purchased_at: formData.value.purchased_at,
    };

    if (formData.value.original_currency !== 'EUR' && formData.value.original_cost) {
        submitData.original_cost = formData.value.original_cost;
        submitData.original_currency = formData.value.original_currency;
    } else {
        submitData.cost = formData.value.cost || formData.value.original_cost;
    }

    router.post(store(), submitData, {
        onSuccess: () => {
            resetForm();
            isAddAssetOpen.value = false;
        },
    });
};

const handleUpdate = () => {
    if (!editingAsset.value) return;

    const submitData: Record<string, string> = {
        name: formData.value.name,
        description: formData.value.description || '',
        tracking_type: formData.value.tracking_type,
        purchased_at: formData.value.purchased_at,
        _method: 'PUT',
    };

    if (formData.value.original_currency !== 'EUR' && formData.value.original_cost) {
        submitData.original_cost = formData.value.original_cost;
        submitData.original_currency = formData.value.original_currency;
    } else {
        submitData.cost = formData.value.cost || formData.value.original_cost;
    }

    router.post(update(editingAsset.value.id), submitData, {
        onSuccess: () => {
            resetForm();
            isEditAssetOpen.value = false;
            editingAsset.value = null;
        },
    });
};

const handleDelete = (id: number) => {
    if (confirm('Are you sure you want to delete this asset?')) {
        router.delete(destroy(id));
    }
};

const handleIncrementUses = (asset: Asset) => {
    router.post(incrementUses(asset.id));
};

const handleDecrementUses = (asset: Asset) => {
    if (asset.uses > 0) {
        router.post(decrementUses(asset.id));
    }
};

const handleIncrementHours = (asset: Asset) => {
    router.post(incrementHours(asset.id));
};

const handleDecrementHours = (asset: Asset) => {
    if (parseFloat(asset.hours) >= 0.5) {
        router.post(decrementHours(asset.id));
    }
};

const openEditDialog = (asset: Asset) => {
    editingAsset.value = asset;
    formData.value = {
        name: asset.name,
        description: asset.description || '',
        cost: asset.cost,
        original_cost: asset.cost,
        original_currency: 'EUR',
        tracking_type: asset.tracking_type,
        purchased_at: asset.purchased_at,
    };
    isEditAssetOpen.value = true;
};

const resetForm = () => {
    formData.value = {
        name: '',
        description: '',
        cost: '',
        original_cost: '',
        original_currency: 'EUR',
        tracking_type: 'uses',
        purchased_at: new Date().toISOString().split('T')[0],
    };
};

const formatCurrency = (amount: string | number) => {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'EUR',
    }).format(typeof amount === 'string' ? parseFloat(amount) : amount);
};
</script>

<template>
    <Head title="Assets" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold tracking-tight">Assets</h1>
                    <p class="text-muted-foreground">
                        Track your assets and calculate cost per use
                    </p>
                </div>
                <Dialog v-model:open="isAddAssetOpen">
                    <DialogTrigger as-child>
                        <Button @click="resetForm">
                            <Package class="mr-2 size-4" />
                            Add Asset
                        </Button>
                    </DialogTrigger>
                    <DialogContent class="max-w-2xl">
                        <DialogHeader>
                            <DialogTitle>Add New Asset</DialogTitle>
                            <DialogDescription>
                                Track items you've purchased and monitor their cost per use
                            </DialogDescription>
                        </DialogHeader>

                        <div class="grid gap-4 py-4">
                            <div class="grid gap-2">
                                <Label for="name">Name *</Label>
                                <Input
                                    id="name"
                                    v-model="formData.name"
                                    placeholder="e.g., Backpack, Laptop, Game"
                                    required
                                />
                            </div>

                            <div class="grid gap-2">
                                <Label for="description">Description</Label>
                                <Textarea
                                    id="description"
                                    v-model="formData.description"
                                    placeholder="Optional details about the asset"
                                    rows="3"
                                />
                            </div>

                            <div class="grid gap-2">
                                <Label for="tracking_type">Track By *</Label>
                                <select
                                    id="tracking_type"
                                    v-model="formData.tracking_type"
                                    class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-base shadow-sm transition-colors file:border-0 file:bg-transparent file:text-sm file:font-medium file:text-foreground placeholder:text-muted-foreground focus-visible:outline-hidden focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50 md:text-sm"
                                >
                                    <option value="uses">Number of Uses</option>
                                    <option value="hours">Hours Played/Used</option>
                                </select>
                                <p class="text-sm text-muted-foreground">
                                    Choose whether to track by number of uses or hours
                                </p>
                            </div>

                            <div class="grid gap-2">
                                <Label for="currency">Currency *</Label>
                                <select
                                    id="currency"
                                    v-model="formData.original_currency"
                                    class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-base shadow-sm transition-colors file:border-0 file:bg-transparent file:text-sm file:font-medium file:text-foreground placeholder:text-muted-foreground focus-visible:outline-hidden focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50 md:text-sm"
                                >
                                    <option value="EUR">EUR (€)</option>
                                    <option value="GBP">GBP (£)</option>
                                    <option value="USD">USD ($)</option>
                                    <option value="CZK">CZK (Kč)</option>
                                </select>
                            </div>

                            <div class="grid gap-2">
                                <Label for="cost">Cost *</Label>
                                <Input
                                    id="cost"
                                    v-model="formData.original_cost"
                                    type="number"
                                    step="0.01"
                                    placeholder="0.00"
                                    required
                                />
                                <p class="text-sm text-muted-foreground">
                                    Will be converted to EUR if another currency is selected
                                </p>
                            </div>

                            <div class="grid gap-2">
                                <Label for="purchased_at">Purchase Date *</Label>
                                <Input
                                    id="purchased_at"
                                    v-model="formData.purchased_at"
                                    type="date"
                                    required
                                />
                            </div>
                        </div>

                        <DialogFooter>
                            <Button type="button" variant="outline" @click="isAddAssetOpen = false">
                                Cancel
                            </Button>
                            <Button type="button" @click="handleSubmit">
                                Save Asset
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </div>

            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                <Card>
                    <CardHeader>
                        <CardTitle>Total Investment</CardTitle>
                        <CardDescription>All assets combined</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">
                            {{ formatCurrency(summary.total_cost) }}
                        </div>
                        <p class="text-sm text-muted-foreground">
                            {{ summary.count }} {{ summary.count === 1 ? 'asset' : 'assets' }}
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Total Uses</CardTitle>
                        <CardDescription>Across all assets</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">
                            {{ summary.total_uses }}
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Total Hours</CardTitle>
                        <CardDescription>Across all assets</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">
                            {{ summary.total_hours }}h
                        </div>
                    </CardContent>
                </Card>
            </div>

            <div v-if="assets.length === 0" class="text-center py-12">
                <Package class="mx-auto size-12 text-muted-foreground mb-4" />
                <p class="text-muted-foreground">No assets tracked yet.</p>
                <Button class="mt-4" @click="isAddAssetOpen = true; resetForm()">
                    Add your first asset
                </Button>
            </div>

            <div v-else class="overflow-hidden rounded-lg border">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50">
                        <tr class="border-b">
                            <th class="px-4 py-3 text-left font-medium">Asset</th>
                            <th class="px-4 py-3 text-left font-medium">Cost</th>
                            <th class="px-4 py-3 text-left font-medium">Purchased</th>
                            <th class="px-4 py-3 text-left font-medium">Metric</th>
                            <th class="px-4 py-3 text-left font-medium">Count</th>
                            <th class="px-4 py-3 text-left font-medium">Cost/Unit</th>
                            <th class="px-4 py-3 text-right font-medium">Tracking</th>
                            <th class="px-4 py-3 text-right font-medium">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="asset in assets"
                            :key="asset.id"
                            class="border-b last:border-0 hover:bg-muted/50"
                        >
                            <td class="px-4 py-3">
                                <div class="font-semibold">{{ asset.name }}</div>
                                <div v-if="asset.description" class="text-xs text-muted-foreground mt-0.5">
                                    {{ asset.description }}
                                </div>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                {{ formatCurrency(asset.cost) }}
                            </td>
                            <td class="px-4 py-3 text-muted-foreground whitespace-nowrap">
                                {{ asset.purchased_at }}
                            </td>
                            <td class="px-4 py-3">
                                <div v-if="asset.tracking_type === 'uses'">Uses</div>
                                <div v-else class="flex items-center gap-1.5">
                                    <Clock class="size-3.5" />
                                    Hours
                                </div>
                            </td>
                            <td class="px-4 py-3 font-semibold whitespace-nowrap">
                                <span v-if="asset.tracking_type === 'uses'">{{ asset.uses }}</span>
                                <span v-else>{{ asset.hours }}h</span>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <span v-if="asset.tracking_type === 'uses' && asset.cost_per_use">
                                    {{ formatCurrency(asset.cost_per_use) }}
                                </span>
                                <span v-else-if="asset.tracking_type === 'hours' && asset.cost_per_hour">
                                    {{ formatCurrency(asset.cost_per_hour) }}
                                </span>
                                <span v-else class="text-muted-foreground">—</span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex gap-1 justify-end">
                                    <Button
                                        v-if="asset.tracking_type === 'uses'"
                                        variant="outline"
                                        size="icon"
                                        class="size-7"
                                        @click="handleDecrementUses(asset)"
                                        :disabled="asset.uses === 0"
                                    >
                                        <Minus class="size-3" />
                                    </Button>
                                    <Button
                                        v-if="asset.tracking_type === 'hours'"
                                        variant="outline"
                                        size="icon"
                                        class="size-7"
                                        @click="handleDecrementHours(asset)"
                                        :disabled="parseFloat(asset.hours) < 0.5"
                                    >
                                        <Minus class="size-3" />
                                    </Button>
                                    <Button
                                        v-if="asset.tracking_type === 'uses'"
                                        variant="outline"
                                        size="icon"
                                        class="size-7"
                                        @click="handleIncrementUses(asset)"
                                    >
                                        <Plus class="size-3" />
                                    </Button>
                                    <Button
                                        v-if="asset.tracking_type === 'hours'"
                                        variant="outline"
                                        size="icon"
                                        class="size-7"
                                        @click="handleIncrementHours(asset)"
                                    >
                                        <Plus class="size-3" />
                                    </Button>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex gap-1 justify-end">
                                    <Button
                                        variant="outline"
                                        size="icon"
                                        class="size-7"
                                        @click="openEditDialog(asset)"
                                    >
                                        <Edit class="size-3" />
                                    </Button>
                                    <Button
                                        variant="destructive"
                                        size="icon"
                                        class="size-7"
                                        @click="handleDelete(asset.id)"
                                    >
                                        <Trash2 class="size-3" />
                                    </Button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Edit Dialog -->
        <Dialog v-model:open="isEditAssetOpen">
            <DialogContent class="max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Edit Asset</DialogTitle>
                    <DialogDescription>
                        Update asset details
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-4 py-4">
                    <div class="grid gap-2">
                        <Label for="edit-name">Name *</Label>
                        <Input
                            id="edit-name"
                            v-model="formData.name"
                            placeholder="e.g., Backpack, Laptop, Game"
                            required
                        />
                    </div>

                    <div class="grid gap-2">
                        <Label for="edit-description">Description</Label>
                        <Textarea
                            id="edit-description"
                            v-model="formData.description"
                            placeholder="Optional details about the asset"
                            rows="3"
                        />
                    </div>

                    <div class="grid gap-2">
                        <Label for="edit-tracking_type">Track By *</Label>
                        <select
                            id="edit-tracking_type"
                            v-model="formData.tracking_type"
                            class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-base shadow-sm transition-colors file:border-0 file:bg-transparent file:text-sm file:font-medium file:text-foreground placeholder:text-muted-foreground focus-visible:outline-hidden focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50 md:text-sm"
                        >
                            <option value="uses">Number of Uses</option>
                            <option value="hours">Hours Played/Used</option>
                        </select>
                    </div>

                    <div class="grid gap-2">
                        <Label for="edit-currency">Currency *</Label>
                        <select
                            id="edit-currency"
                            v-model="formData.original_currency"
                            class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-base shadow-sm transition-colors file:border-0 file:bg-transparent file:text-sm file:font-medium file:text-foreground placeholder:text-muted-foreground focus-visible:outline-hidden focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50 md:text-sm"
                        >
                            <option value="EUR">EUR (€)</option>
                            <option value="GBP">GBP (£)</option>
                            <option value="USD">USD ($)</option>
                            <option value="CZK">CZK (Kč)</option>
                        </select>
                    </div>

                    <div class="grid gap-2">
                        <Label for="edit-cost">Cost *</Label>
                        <Input
                            id="edit-cost"
                            v-model="formData.original_cost"
                            type="number"
                            step="0.01"
                            placeholder="0.00"
                            required
                        />
                    </div>

                    <div class="grid gap-2">
                        <Label for="edit-purchased_at">Purchase Date *</Label>
                        <Input
                            id="edit-purchased_at"
                            v-model="formData.purchased_at"
                            type="date"
                            required
                        />
                    </div>
                </div>

                <DialogFooter>
                    <Button type="button" variant="outline" @click="isEditAssetOpen = false">
                        Cancel
                    </Button>
                    <Button type="button" @click="handleUpdate">
                        Update Asset
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
