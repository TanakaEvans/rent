// Safe route helper - returns '#' if route doesn't exist
export const safeRoute = (name, params = {}) => {
    try {
        return route(name, params);
    } catch (e) {
        console.warn(`Route '${name}' not found`);
        return '#';
    }
};

// Safe current route checker
export const isCurrentRoute = (pattern) => {
    try {
        return route().current(pattern);
    } catch (e) {
        return false;
    }
};
