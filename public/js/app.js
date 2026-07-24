import { ApiService, loadPackages } from './api.js';

document.addEventListener('DOMContentLoaded', () => {
    const packagesContainer = document.getElementById('packages-container');
    if (packagesContainer) {
        loadPackages('packages-container', 12).then((items) => {
            if (!items || !items.length) return;
            const grid = document.createElement('div');
            grid.className = 'pentagon-packages-grid';
            grid.style.cssText = 'display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:1.5rem';
            items.forEach((pkg) => {
                const card = document.createElement('article');
                card.className = 'pentagon-package-card';
                card.innerHTML = `
                    <a href="${pkg.url || '#'}" style="text-decoration:none;color:inherit">
                        <img src="${pkg.image}" alt="" style="width:100%;height:200px;object-fit:cover;border-radius:12px 12px 0 0" loading="lazy">
                        <div style="padding:1rem;font-family:Poppins,sans-serif">
                            <h3 style="margin:0 0 .5rem;color:#0A4322">${pkg.title}</h3>
                            <p style="font-size:13px;color:#666;margin:0 0 .5rem">${(pkg.description || '').slice(0, 100)}…</p>
                            <strong style="color:#228B22">${pkg.price_label || 'KES ' + Number(pkg.price).toLocaleString()}</strong>
                        </div>
                    </a>`;
                grid.appendChild(card);
            });
            packagesContainer.innerHTML = '';
            packagesContainer.appendChild(grid);
        }).catch((err) => console.error('Failed to load packages:', err));
    }
});
