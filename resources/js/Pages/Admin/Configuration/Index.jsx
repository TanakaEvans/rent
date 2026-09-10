import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import { Settings2, Save, History, Check, TriangleAlert, Lock, ShieldAlert } from 'lucide-react';
import AdminLayout from '@/Layouts/AdminLayout';
import StatusBadge from '@/Components/Shared/StatusBadge';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import { Checkbox } from '@/Components/ui/checkbox';
import { Switch } from '@/Components/ui/switch';

const prettyJson = (value) => {
    try {
        return JSON.stringify(JSON.parse(value || '[]'), null, 2);
    } catch {
        return value || '';
    }
};

const toPayloadValue = (row, raw) => {
    if (row.type === 'json') {
        try {
            return JSON.parse(raw || '[]');
        } catch {
            return null;
        }
    }
    if (row.type === 'boolean') return raw === true || raw === 'true';
    if (row.type === 'integer' || row.type === 'decimal') return raw === '' || raw === null || raw === undefined ? '' : Number(raw);
    return raw;
};

const riskBadge = (risk) => (risk === 'critical' || risk === 'high' ? <ShieldAlert className="h-4 w-4 text-rose-500" /> : <Lock className="h-3.5 w-3.5 text-muted-foreground/60" />);

function ConfigRow({ row, value, onChange }) {
    if (row.type === 'boolean') {
        return <Switch checked={value === true || value === '1'} onCheckedChange={(v) => onChange(row, v)} />;
    }
    if (row.type === 'json') {
        return (
            <Textarea
                className="min-h-20 font-mono text-xs"
                value={value}
                onChange={(e) => onChange(row, e.target.value)}
                placeholder="[]"
            />
        );
    }
    return (
        <Input
            className="font-mono text-sm"
            type={row.type === 'integer' ? 'number' : row.type === 'decimal' ? 'number' : 'text'}
            step={row.type === 'decimal' ? '0.01' : undefined}
            value={value}
            disabled={!row.is_editable}
            onChange={(e) => onChange(row, e.target.value)}
        />
    );
}

export default function ConfigurationCentre({ groups = {}, features = [], plans = [], audits = [] }) {
    const [values, setValues] = useState(() => {
        const initial = {};
        Object.values(groups || {}).forEach((rows) => {
            rows.forEach((row) => {
                if (row.type === 'json') {
                    initial[row.key] = row.value ? prettyJson(row.value) : '[]';
                } else if (row.type === 'boolean') {
                    initial[row.key] = row.value === '1' || row.value === 'true';
                } else {
                    initial[row.key] = row.value ?? '';
                }
            });
        });
        return initial;
    });
    const [reason, setReason] = useState('');

    const setValue = (row, raw) => setValues((prev) => ({ ...prev, [row.key]: raw }));

    const save = () => {
        const payload = {};
        Object.values(groups || {}).forEach((rows) => {
            rows.forEach((row) => {
                if (!row.is_editable) return;
                const converted = toPayloadValue(row, values[row.key]);
                if (converted !== null && converted !== undefined && converted !== '') {
                    payload[row.key] = converted;
                }
            });
        });
        router.patch(route('admin.configuration.update'), {
            values: payload,
            reason,
        });
    };

    const groupOrder = ['subscriptions', 'numbering', 'featured', 'payments', 'late_fees'].filter((g) => groups[g]);

    return (
        <AdminLayout title="Configuration Centre">
            <Head title="Configuration Centre" />

            <div className="mb-7 flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h2 className="text-balance text-2xl font-extrabold tracking-tight sm:text-3xl">Configuration Centre</h2>
                    <p className="mt-1 max-w-2xl text-sm text-muted-foreground">
                        Commercial rules are data, not code. Change pricing, grace periods, proration, suspension behaviour, numbering and approval
                        rules here — no developer needed. Every change is audited.
                    </p>
                </div>
            </div>

            {groupOrder.map((group) => (
                <div key={group} className="surface mb-6">
                    <div className="grid gap-3 border-b border-border px-5 py-4 sm:grid-cols-[220px_1fr]">
                        <h3 className="flex items-center gap-2 text-sm font-extrabold uppercase tracking-wider text-foreground">
                            <Settings2 className="h-4 w-4 text-emerald-500" />
                            {group}
                        </h3>
                        <p className="text-xs text-muted-foreground">{group === 'subscriptions' ? 'Plan lifecycle, quota and lifecycle rules' : group === 'numbering' ? 'Invoice and receipt numbering' : group === 'payments' ? 'Payment and POP approval rules' : group === 'late_fees' ? 'Arrears and late-fee policy' : 'Featured placement pricing and windows'}</p>
                    </div>

                    {groups[group].map((row) => (
                        <div key={row.key} className="grid items-center gap-3 border-b border-border/60 px-5 py-4 last:border-0 sm:grid-cols-[minmax(0,1fr)_320px]">
                            <div className="flex items-start gap-2.5">
                                <div className="mt-0.5">{riskBadge(row.risk)}</div>
                                <div className="min-w-0">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <span className="text-sm font-bold text-foreground">{row.label}</span>
                                        <StatusBadge status={row.risk} />
                                        <span className="rounded-md bg-muted px-1.5 py-0.5 font-mono text-[11px] text-muted-foreground">{row.key}</span>
                                        {!row.is_editable && <Lock className="h-3.5 w-3.5 text-muted-foreground/70" />}
                                    </div>
                                    {row.description && <p className="mt-1 text-xs text-muted-foreground">{row.description}</p>}
                                    {!row.is_editable && <p className="mt-1 text-[11px] font-medium text-rose-500">Locked — change in code only.</p>}
                                </div>
                            </div>
                            <ConfigRow row={row} value={values[row.key]} onChange={setValue} />
                        </div>
                    ))}
                </div>
            ))}

            <div className="surface mb-6 p-5">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div className="max-w-xl flex-1">
                        <Label htmlFor="config-reason">Reason for change (audit trail)</Label>
                        <Input id="config-reason" className="mt-1.5" value={reason} onChange={(e) => setReason(e.target.value)} placeholder="e.g. We now allow 14 days of grace on annual plans" />
                    </div>
                    <Button onClick={save}>
                        <Save className="size-4" /> Save changes
                    </Button>
                </div>
                <p className="mt-3 flex items-center gap-1.5 text-[11px] text-muted-foreground">
                    <ShieldAlert className="h-3.5 w-3.5 text-rose-400" />
                    High/critical-risk changes record the acting staff member as the approver. Existing invoices and cycles are never rewritten.
                </p>
            </div>

            <div className="surface mb-6">
                <div className="border-b border-border px-5 py-4">
                    <h3 className="text-sm font-extrabold uppercase tracking-wider text-foreground">Feature Catalogue</h3>
                    <p className="mt-1 text-xs text-muted-foreground">What each plan grants, toggled independently of plan pricing.</p>
                </div>
                <div className="grid gap-4 p-5 lg:grid-cols-2">
                    {plans.map((plan) => (
                        <FeatureToggler key={plan.id} plan={plan} features={features} />
                    ))}
                </div>
            </div>

            <div className="surface mb-6">
                <div className="flex items-center gap-2 border-b border-border px-5 py-4">
                    <History className="h-4 w-4 text-emerald-500" />
                    <h3 className="text-sm font-extrabold uppercase tracking-wider text-foreground">Recent configuration changes</h3>
                </div>
                {audits.length === 0 ? (
                    <p className="px-5 py-6 text-sm text-muted-foreground">No changes yet — baseline values were seeded without audit rows.</p>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[640px] text-left text-sm">
                            <thead>
                                <tr className="border-b border-border text-xs uppercase tracking-wider text-muted-foreground">
                                    <th className="px-5 py-3 font-bold">Key</th>
                                    <th className="px-5 py-3 font-bold">Old</th>
                                    <th className="px-5 py-3 font-bold">New</th>
                                    <th className="px-5 py-3 font-bold">Changed by</th>
                                    <th className="px-5 py-3 font-bold">Reason</th>
                                    <th className="px-5 py-3 font-bold">When</th>
                                </tr>
                            </thead>
                            <tbody>
                                {audits.map((audit) => (
                                    <tr key={audit.id} className="border-b border-border/60 last:border-0">
                                        <td className="px-5 py-3 font-mono text-xs text-foreground">{audit.key}</td>
                                        <td className="max-w-40 truncate px-5 py-3 font-mono text-xs text-muted-foreground">{audit.old_value}</td>
                                        <td className="max-w-40 truncate px-5 py-3 font-mono text-xs text-emerald-600">{audit.new_value}</td>
                                        <td className="px-5 py-3 text-muted-foreground">{audit.changed_by?.name ?? '—'}</td>
                                        <td className="max-w-56 truncate px-5 py-3 text-xs text-muted-foreground">{audit.reason ?? '—'}</td>
                                        <td className="px-5 py-3 text-xs text-muted-foreground">{audit.created_at}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </AdminLayout>
    );
}

function FeatureToggler({ plan, features }) {
    const [selected, setSelected] = useState(() => new Set((plan.features || []).map((f) => f.id)));
    const [dirty, setDirty] = useState(false);

    const toggle = (id) => {
        setSelected((prev) => {
            const next = new Set(prev);
            if (next.has(id)) next.delete(id);
            else next.add(id);
            setDirty(true);
            return next;
        });
    };

    const save = () => {
        router.put(route('admin.subscriptions.plans.features', plan.id), { feature_ids: [...selected] });
        setDirty(false);
    };

    return (
        <div className="rounded-xl border border-border p-4">
            <div className="mb-3 flex items-center justify-between gap-3">
                <div className="flex items-center gap-2 font-bold text-foreground">
                    {plan.name}
                    <span className="text-xs font-semibold text-muted-foreground">
                        {money(plan.price)}
                        {plan.billing_cycle === 'annual' ? '/yr' : '/mo'}
                    </span>
                </div>
                <Button variant="outline" size="sm" disabled={!dirty} onClick={save}>
                    <Check className="size-3.5" /> Save grants
                </Button>
            </div>
            <div className="grid gap-1.5">
                {features.map((feature) => (
                    <label key={feature.id} className="flex cursor-pointer items-center justify-between gap-3 rounded-lg px-2 py-1.5 transition-colors hover:bg-muted/40">
                        <div className="min-w-0">
                            <div className="text-sm font-medium text-foreground">{feature.label}</div>
                            <div className="truncate text-[11px] text-muted-foreground">{feature.description}</div>
                        </div>
                        <Checkbox checked={selected.has(feature.id)} onCheckedChange={() => toggle(feature.id)} />
                    </label>
                ))}
            </div>
        </div>
    );
}

const money = (value) => `$${Number(value || 0).toFixed(2)}`;