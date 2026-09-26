import L from 'leaflet';
import { formatPrice } from '@/lib/listing';

export const DEFAULT_CENTER = { lat: -17.8292, lng: 31.0522, zoom: 12 };

export const escapeHtml = (value) =>
    String(value ?? '').replace(/[&<>"']/g, (ch) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[ch]);

/** Street map (CARTO Voyager) and satellite imagery with place labels (Esri). */
export const createBaseLayers = () => ({
    map: L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
        maxZoom: 20,
        subdomains: 'abcd',
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> &copy; <a href="https://carto.com/attributions">CARTO</a>',
    }),
    satellite: L.layerGroup([
        L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
            maxZoom: 19,
            attribution: 'Imagery &copy; Esri',
        }),
        L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/Reference/World_Boundaries_and_Places/MapServer/tile/{z}/{y}/{x}', {
            maxZoom: 19,
        }),
    ]),
});

/** Creates a Leaflet map with the ZimRent defaults: zoom control bottom-right, no Leaflet prefix. */
export const createMap = (element, options = {}) => {
    const map = L.map(element, { zoomControl: false, ...options });
    L.control.zoom({ position: 'bottomright' }).addTo(map);
    map.attributionControl.setPrefix('');
    return map;
};

export const hasCoordinates = (property) =>
    Number.isFinite(Number.parseFloat(property?.latitude)) && Number.isFinite(Number.parseFloat(property?.longitude));

export const pricePinIcon = (property, active = false) =>
    L.divIcon({
        className: '',
        html: `<div class="dz-map-pin${active ? ' is-active' : ''}">${escapeHtml(formatPrice(property.price, property.currency))}</div>`,
        iconSize: [0, 0],
        iconAnchor: [0, 0],
    });

export const homePinIcon = () =>
    L.divIcon({
        className: '',
        html: '<div class="dz-home-pin"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V21h14V9.5"/><path d="M10 21v-6h4v6"/></svg></div>',
        iconSize: [0, 0],
        iconAnchor: [0, 0],
    });

/** Rich popup card for a listing: photo, price, title, location and a details link. */
export const propertyPopupHtml = (property, { href, suffix = '/mo', meta = '' }) => {
    const image = property.images?.[0]?.path;
    const location = [property.suburb, property.city].filter(Boolean).join(', ');
    return `<a class="dz-pop" href="${escapeHtml(href)}">
        ${image ? `<img class="dz-pop__img" src="${escapeHtml(image)}" alt="" />` : ''}
        <div class="dz-pop__body">
            <div class="dz-pop__price">${escapeHtml(formatPrice(property.price, property.currency))} <small>${escapeHtml(suffix)}</small></div>
            <div class="dz-pop__title">${escapeHtml(property.title)}</div>
            <div class="dz-pop__meta">${escapeHtml([location, meta].filter(Boolean).join(' · '))}</div>
            <div class="dz-pop__cta">View details &rarr;</div>
        </div>
    </a>`;
};
