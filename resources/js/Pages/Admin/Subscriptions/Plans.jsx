import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { CreditCard, Plus, Pencil, Save, Archive, X, Check } from 'lucide-react';
import AdminLayout from '@/Layouts/AdminLayout';
import StatusBadge from '@/Components/Shared/StatusBadge';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Checkbox } from '@/Components/ui/checkbox';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';

const money = (value) => `$${Number(value || 0).toFixed(2)}`;
const limitLabel = (limit) => (limit === null || limit === undefined ? 'Unlimited' : limit);

function PlanEditor({ plan = null, onDone }) {
    const create = !plan;
    const [name, setName] = useState(plan?.name || '');
    const [price, setPrice] = useState(plan?.price ?? '');
    const [cycle, setCycle] = useState(plan?.billing_cycle || 'monthly');
    const [listingLimit, setListingLimit] = useState(plan?.listing_limit ?? '');
    const [featuredSlots, setFeaturedSlots] = useState(plan?.featured_slots ?? 0);
    const [supportTier, setSupportTier] = useState(plan?.support_tier || 'standard');
    const [analytics, setAnalytics] = useState(plan?.analytics_enabled ?? false);

    const submit = () => {
        const payload = {
            name,
            price,
            billing_cycle: cycle,
            listing_limit: listingLimit === '' ? null : listingLimit,
            featured_slots: featuredSlots,
            support_tier: supportTier,
            analytics_enabled: analytics,
        };
        if (create) {
            router.post(route('admin.subscriptions.plans.store'), payload);
        } else {
            router.patch(route('admin.subscriptions.plans.update', plan.id), payload, { onSuccess: () => onDone() });
        }
    };

    return (
        <div className="space-y-3 border-t border-border pt-4">
            <div className="grid gap-3 sm:grid-cols-3">
                <div className="space-y-1.5">
                    <Label htmlFor={`${create ? 'new' : plan.id}-name`}>Name</Label>
                    <Input id={`${create ? 'new' : plan.id}-name`} value={name} onChange={(e) => setName(e.target.value)} placeholder="Professional" />
                </div>
                <div className="space-y-1.5">
                    <Label htmlFor={`${create ? 'new' : plan.id}-price`}>Monthly / annual price</Label>
                    <Input id={`${create ? 'new' : plan.id}-price`} type="number" min="0" step="0.01" value={price} onChange={(e) => setPrice(e.target.value)} placeholder="0.00" />
                </div>
                <div className="space-y-1.5">
                    <Label htmlFor={`${create ? 'new' : plan.id}-cycle`}>Billing cycle</Label>
                    <Select value={cycle} onValueChange={setCycle}>
                        <SelectTrigger className="w-full" id={`${create ? 'new' : plan.id}-cycle`}>
                            <SelectValue placeholder="Cycle" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="monthly">Monthly</SelectItem>
                            <SelectItem value="annual">Annual</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div className="space-y-1.5">
                    <Label htmlFor={`${create ? 'new' : plan.id}-limit`}>Listing limit (blank = unlimited)</Label>
                    <Input id={`${create ? 'new' : plan.id}-limit`} type="number" min="0" value={listingLimit} onChange={(e) => setListingLimit(e.target.value === '' ? '' : Number(e.target.value))} />
                </div>
                <div className="space-y-1.5">
                    <Label htmlFor={`${create ? 'new' : plan.id}-slots`}>Featured slots</Label>
                    <Input id={`${create ? 'new' : plan.id}-slots`} type="number" min="0" value={featuredSlots} onChange={(e) => setFeaturedSlots(Number(e.target.value) || 0)} />
                </div>
                <div className="space-y-1.5">
                    <Label htmlFor={`${create ? 'new' : plan.id}-tier`}>Support tier</Label>
                    <Input id={`${create ? 'new' : plan.id}-tier`} value={supportTier} onChange={(e) => setSupportTier(e.target.value)} placeholder="standard" />
                </div>
            </div>

            <div className="flex items-center justify-between gap-4">
                <label className="flex items-center gap-2 text-sm font-medium text-foreground">
                    <Checkbox checked={analytics} onCheckedChange={setAnalytics} />
                    Analytics enabled
                </label>
                <div className="flex gap-2">
                    {!create && (
                        <Button variant="ghost" size="sm" onClick={onDone}>
                            <X className="size-4" /> Cancel
                        </Button>
                    )}
                    <Button size="sm" onClick={submit}>
                        {create ? (<><Plus className="size-4" /> Create plan</>) : (<><Save className="size-4" /> Save changes</>)}
                    </Button>
                </div>
            </div>
        </div>
    );
}

export default function AdminPlans({ plans = [] }) {
    const [creating, setCreating] = useState(false);
    const [editingId, setEditingId] = useState(null);

    const archive = (plan) => {
        const message = 'Archive this plan? Existing subscribers keep their plan, but no new subscriptions can select it.';
        if (window.confirm(message)) {
            router.delete(route('admin.subscriptions.plans.destroy', plan.id));
        }
    };

    return (
        <AdminLayout title="Subscription Plans">
            <Head title="Subscription Plans" />

            <div className="mb-7 flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h2 className="text-balance text-2xl font-extrabold tracking-tight sm:text-3xl">Subscription Plans</h2>
                    <p className="mt-1 text-sm text-muted-foreground">Define the owner subscription catalog, listing quotas and pricing for each tier.</p>
                </div>
                <Button onClick={() => { setCreating((v) => !v); setEditingId(null); }}>
                    <Plus className="size-4" /> New plan
                </Button>
            </div>

            <div className="surface overflow-hidden">
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[720px] text-left text-sm">
                        <thead>
                            <tr className="border-b border-border text-xs uppercase tracking-wider text-muted-foreground">
                                <th className="px-5 py-3 font-bold">Plan</th>
                                <th className="px-5 py-3 font-bold">Price</th>
                                <th className="px-5 py-3 font-bold">Limit</th>
                                <th className="px-5 py-3 font-bold">Featured</th>
                                <th className="px-5 py-3 font-bold">Support</th>
                                <th className="px-5 py-3 font-bold">Analytics</th>
                                <th className="px-5 py-3 font-bold">Subscribers</th>
                                <th className="px-5 py-3 font-bold">Status</th>
                                <th className="px-5 py-3 text-right font-bold">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {creating && (
                                <tr className="border-b border-border bg-muted/30">
                                    <td colSpan={9} className="px-5 py-4">
                                        <PlanEditor onDone={() => setCreating(false)} />
                                    </td>
                                </tr>
                            )}
                            {plans.map((plan) => (
                                <PlanRow key={plan.id} plan={plan} editing={editingId === plan.id} onEdit={() => { setEditingId(plan.id); setCreating(false); }} onDone={() => setEditingId(null)} onArchive={() => archive(plan)} />
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>

            {plans.length === 0 && !creating && (
                <p className="mt-8 text-center text-sm text-muted-foreground">No plans yet. Create your first subscription plan to get started.</p>
            )}
        </AdminLayout>
    );
}

function PlanRow({ plan, editing, onEdit, onDone, onArchive }) {
    return (
        <>
            <tr className="border-b border-border/60 last:border-0">
                <td className="px-5 py-3.5">
                    <span className="flex items-center gap-2 font-bold text-foreground">
                        <CreditCard className="h-4 w-4 text-emerald-500" />
                        {plan.name}
                    </span>
                </td>
                <td className="px-5 py-3.5 font-semibold text-foreground">
                    {money(plan.price)}
                    <span className="text-xs text-muted-foreground">{plan.billing_cycle === 'annual' ? '/yr' : '/mo'}</span>
                </td>
                <td className="px-5 py-3.5 text-muted-foreground">{limitLabel(plan.listing_limit)}</td>
                <td className="px-5 py-3.5 text-muted-foreground">{plan.featured_slots}</td>
                <td className="px-5 py-3.5 capitalize text-muted-foreground">{plan.support_tier}</td>
                <td className="px-5 py-3.5 text-muted-foreground">
                    {plan.analytics_enabled ? <Check className="h-4 w-4 text-emerald-500" /> : '—'}
                </td>
                <td className="px-5 py-3.5 font-semibold text-foreground">{plan.active_subscriptions}</td>
                <td className="px-5 py-3.5"><StatusBadge status={plan.status} /></td>
                <td className="px-5 py-3.5">
                    <div className="flex justify-end gap-2">
                        {!editing && (
                            <Button variant="outline" size="sm" onClick={onEdit}>
                                <Pencil className="size-3.5" /> Edit
                            </Button>
                        )}
                        <Button variant="ghost" size="sm" className="text-rose-600" onClick={onArchive}>
                            <Archive className="size-3.5" /> Archive
                        </Button>
                    </div>
                </td>
            </tr>
            {editing && (
                <tr className="border-b border-border/60 bg-muted/30">
                    <td colSpan={9} className="px-5 py-4">
                        <PlanEditor plan={plan} onDone={onDone} />
                    </td>
                </tr>
            )}
        </>
    );
}