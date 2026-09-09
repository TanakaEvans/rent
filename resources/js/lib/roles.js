export const dashboardRouteFor = (roles = []) => {
    const names = roles.map((role) => role.name);
    if (names.some((name) => ['Admin', 'Superuser'].includes(name))) return 'admin.dashboard';
    if (names.includes('Owner')) return 'owner.dashboard';
    if (names.includes('Tenant')) return 'tenant.dashboard';
    return 'dashboard';
};