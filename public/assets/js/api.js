const BASE = "/api";

async function apiFetch(path, options = {}) {
    const token = localStorage.getItem("auth_token");
    const headers = {
        "Content-Type": "application/json",
        ...(options.headers || {}),
    };
    if (token) headers["Authorization"] = "Bearer " + token;
    const res = await fetch(BASE + path, { ...options, headers });
    const data = await res.json();
    if (!data.success) throw new Error(data.error || "Erreur API");
    return data.data;
}

// Upload d'image avec FormData (spécial)
async function apiUpload(path, file) {
    const token = localStorage.getItem("auth_token");
    const fd = new FormData();
    fd.append("image", file);
    const res = await fetch(BASE + path, {
        method: "POST",
        headers: { Authorization: "Bearer " + token },
        body: fd,
    });
    const data = await res.json();
    if (!data.success) throw new Error(data.error || "Erreur upload");
    return data.data;
}

const api = {
    // ========== ROUTES PUBLIQUES ==========
    getSettings: () => apiFetch("/settings"),
    getCategories: () => apiFetch("/categories"),
    getProducts: (categoryId) => {
        const url = categoryId
            ? "/products?category_id=" + categoryId
            : "/products";
        return apiFetch(url);
    },
    getOrder: (token) => apiFetch("/orders/" + token),
    getMyOrders: () => apiFetch("/orders/mine"),
    register: (body) =>
        apiFetch("/auth/register", {
            method: "POST",
            body: JSON.stringify(body),
        }),
    login: (body) =>
        apiFetch("/auth/login", { method: "POST", body: JSON.stringify(body) }),
    forgotPassword: (body) =>
        apiFetch("/auth/forgot", {
            method: "POST",
            body: JSON.stringify(body),
        }),
    resetPassword: (body) =>
        apiFetch("/auth/reset", { method: "POST", body: JSON.stringify(body) }),
    createOrder: (body) =>
        apiFetch("/orders", { method: "POST", body: JSON.stringify(body) }),
    createIntent: (body) =>
        apiFetch("/payment/create-intent", {
            method: "POST",
            body: JSON.stringify(body),
        }),

    // ========== ROUTES ADMIN ==========
    // Auth
    adminLogin: (body) =>
        apiFetch("/auth/login", { method: "POST", body: JSON.stringify(body) }),

    // Commandes
    getAdminOrders: (params) => {
        let url = "/admin/orders";
        if (params && Object.keys(params).length) {
            url += "?" + new URLSearchParams(params);
        }
        return apiFetch(url);
    },
    updateOrderStatus: (id, status, reason = null) => {
        const body = { status };
        if (reason) body.cancellation_reason = reason;
        return apiFetch(`/admin/orders/${id}`, {
            method: "PATCH",
            body: JSON.stringify(body),
        });
    },
    assignDriver: (id, driverId) =>
        apiFetch(`/admin/orders/${id}/assign`, {
            method: "PATCH",
            body: JSON.stringify({ driver_id: driverId }),
        }),
    getDrivers: () => apiFetch("/admin/orders/drivers"),

    // Produits
    getAdminProducts: () => apiFetch("/admin/products"),
    createProduct: (data) =>
        apiFetch("/admin/products", {
            method: "POST",
            body: JSON.stringify(data),
        }),
    updateProduct: (id, data) =>
        apiFetch(`/admin/products/${id}`, {
            method: "PUT",
            body: JSON.stringify(data),
        }),
    deleteProduct: (id) =>
        apiFetch(`/admin/products/${id}`, { method: "DELETE" }),

    // Catégories
    getAdminCategories: () => apiFetch("/admin/categories"),
    createCategory: (data) =>
        apiFetch("/admin/categories", {
            method: "POST",
            body: JSON.stringify(data),
        }),
    updateCategory: (id, data) =>
        apiFetch(`/admin/categories/${id}`, {
            method: "PUT",
            body: JSON.stringify(data),
        }),
    deleteCategory: (id) =>
        apiFetch(`/admin/categories/${id}`, { method: "DELETE" }),

    // Frais de livraison
    getDeliveryFees: () => apiFetch("/admin/delivery-fees"),
    createDeliveryFee: (data) =>
        apiFetch("/admin/delivery-fees", {
            method: "POST",
            body: JSON.stringify(data),
        }),
    updateDeliveryFee: (id, data) =>
        apiFetch(`/admin/delivery-fees/${id}`, {
            method: "PUT",
            body: JSON.stringify(data),
        }),
    deleteDeliveryFee: (id) =>
        apiFetch(`/admin/delivery-fees/${id}`, { method: "DELETE" }),

    // Options
    getOptions: (productId) =>
        apiFetch(`/admin/options?product_id=${productId}`),
    createOption: (data) =>
        apiFetch("/admin/options", {
            method: "POST",
            body: JSON.stringify(data),
        }),
    updateOption: (id, data) =>
        apiFetch(`/admin/options/${id}`, {
            method: "PUT",
            body: JSON.stringify(data),
        }),
    deleteOption: (id) =>
        apiFetch(`/admin/options/${id}`, { method: "DELETE" }),

    // Settings
    getAdminSettings: () => apiFetch("/admin/settings"),
    updateAdminSettings: (data) =>
        apiFetch("/admin/settings", {
            method: "PUT",
            body: JSON.stringify(data),
        }),

    // Stats
    getStats: () => apiFetch("/admin/stats"),

    // Users
    getUsers: () => apiFetch("/admin/users"),
    createUser: (data) =>
        apiFetch("/admin/users", {
            method: "POST",
            body: JSON.stringify(data),
        }),
    updateUserRole: (id, role) =>
        apiFetch(`/admin/users/${id}`, {
            method: "PATCH",
            body: JSON.stringify({ role }),
        }),
    deleteUser: (id) => apiFetch(`/admin/users/${id}`, { method: "DELETE" }),
    resetUserPassword: (id) =>
        apiFetch(`/admin/users/${id}/reset-password`, { method: "POST" }),

    // Upload
    uploadProductImage: (file) => apiUpload("/admin/upload", file),

    // Horaires
    getOpeningHours: () => apiFetch("/admin/opening-hours"),
    updateOpeningHours: (data) =>
        apiFetch("/admin/opening-hours", {
            method: "PUT",
            body: JSON.stringify(data),
        }),

    // Logs
    getAdminLogs: (limit = 100) => apiFetch(`/admin/logs?limit=${limit}`),
};
