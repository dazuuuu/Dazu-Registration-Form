// Pentagon UI API client — points at Dazu Registration-System backend.
const API_BASE_URL = (document.querySelector('meta[name="pentagon-api-base"]')?.content || '/api').replace(/\/$/, '');

export const ApiService = {
    async get(endpoint) {
        const response = await fetch(`${API_BASE_URL}/${endpoint.replace(/^\//, '')}`);
        if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
        return await response.json();
    },

    async post(endpoint, data) {
        const response = await fetch(`${API_BASE_URL}/${endpoint.replace(/^\//, '')}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
            body: JSON.stringify(data),
        });
        if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
        return await response.json();
    },
};

export async function loadPackages(containerId = 'packages-container', limit = 12) {
    const container = document.getElementById(containerId);
    if (!container) return;
    const res = await ApiService.get(`packages.php?limit=${limit}`);
    return res.data || [];
}

export async function submitContact(payload) {
    return ApiService.post('contact.php', payload);
}
