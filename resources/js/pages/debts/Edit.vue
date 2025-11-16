<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { update } from '@/actions/App/Http/Controllers/DebtController';
import { index } from '@/routes/debts';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import InputError from '@/components/InputError.vue';
import { Form } from '@inertiajs/vue3';
import type { BreadcrumbItem } from '@/types';

interface Debt {
    id: number;
    debtor_name: string;
    amount: string;
    type: 'owed_to_me' | 'i_owe';
    description: string | null;
    due_date: string | null;
    is_paid: boolean;
}

interface Props {
    debt: Debt;
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Debt Tracker',
        href: index().url,
    },
    {
        title: 'Edit Debt',
        href: '#',
    },
];
</script>

<template>
    <Head title="Edit Debt" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6 max-w-2xl">
            <div>
                <h1 class="text-3xl font-bold tracking-tight">Edit Debt</h1>
                <p class="text-muted-foreground">
                    Update the debt information
                </p>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>Debt Details</CardTitle>
                    <CardDescription>
                        Modify the information about the debt
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <Form
                        v-bind="update.form(debt.id)"
                        class="space-y-6"
                        v-slot="{ errors, processing }"
                    >
                        <div class="grid gap-2">
                            <Label for="debtor_name">Person or Entity</Label>
                            <Input
                                id="debtor_name"
                                name="debtor_name"
                                :default-value="debt.debtor_name"
                                placeholder="e.g., John Doe"
                                required
                            />
                            <InputError :message="errors.debtor_name" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="amount">Amount</Label>
                            <Input
                                id="amount"
                                name="amount"
                                type="number"
                                step="0.01"
                                min="0.01"
                                :default-value="debt.amount"
                                placeholder="0.00"
                                required
                            />
                            <InputError :message="errors.amount" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="type">Debt Type</Label>
                            <select
                                id="type"
                                name="type"
                                class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium file:text-foreground placeholder:text-muted-foreground focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                                required
                            >
                                <option value="owed_to_me" :selected="debt.type === 'owed_to_me'">
                                    Owed to Me
                                </option>
                                <option value="i_owe" :selected="debt.type === 'i_owe'">
                                    I Owe
                                </option>
                            </select>
                            <InputError :message="errors.type" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="description">Description (Optional)</Label>
                            <Textarea
                                id="description"
                                name="description"
                                :default-value="debt.description"
                                placeholder="What is this debt for?"
                                rows="3"
                            />
                            <InputError :message="errors.description" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="due_date">Due Date (Optional)</Label>
                            <Input
                                id="due_date"
                                name="due_date"
                                type="date"
                                :default-value="debt.due_date"
                            />
                            <InputError :message="errors.due_date" />
                        </div>

                        <div class="flex items-center gap-2">
                            <input
                                id="is_paid"
                                name="is_paid"
                                type="checkbox"
                                value="1"
                                :checked="debt.is_paid"
                                class="h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary"
                            />
                            <Label for="is_paid" class="cursor-pointer">
                                Mark as paid
                            </Label>
                        </div>

                        <div class="flex gap-2">
                            <Button type="submit" :disabled="processing">
                                {{ processing ? 'Updating...' : 'Update Debt' }}
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                @click="$inertia.visit(index())"
                            >
                                Cancel
                            </Button>
                        </div>
                    </Form>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
