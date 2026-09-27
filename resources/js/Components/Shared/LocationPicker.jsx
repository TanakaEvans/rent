import { useEffect, useRef, useState } from 'react';
import PropTypes from 'prop-types';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { LocateFixed, MapPin } from 'lucide-react';
import { createBaseLayers, createMap, homePinIcon, DEFAULT_CENTER } from '@/lib/map';
import MapStyleToggle from '@/Components/Shared/MapStyleToggle';

const asNumber = (value) => {
    const parsed = Number.parseFloat(value);
    return Number.isFinite(parsed) ? parsed : null;
};

/**
 * Click-to-set map pin with a "use my current location" shortcut, used by the
 * owner listing form to capture the mandatory precise coordinates. The parent
 * owns the latitude/longitude values; this component only reports changes.
 */
export default function LocationPicker({ latitude, longitude, onChange, error }) {
    const mountRef = useRef(null);
    const mapRef = useRef(null);
    const markerRef = useRef(null);
    const layersRef = useRef(null);
    const onChangeRef = useRef(onChange);
    const [style, setStyle] = useState('map');
    const [locating, setLocating] = useState(false);
    const [geoError, setGeoError] = useState(null);

    onChangeRef.current = onChange;

    const lat = asNumber(latitude);
    const lng = asNumber(longitude);
    const hasPin = lat !== null && lng !== null;

    const placeMarker = (nextLat, nextLng) => {
        const map = mapRef.current;
        if (!map) return;
        if (markerRef.current) {
            markerRef.current.setLatLng([nextLat, nextLng]);
        } else {
            markerRef.current = L.marker([nextLat, nextLng], { icon: homePinIcon(), keyboard: false }).addTo(map);
        }
    };

    // Mount once. Click anywhere on the map to drop / move the pin.
    useEffect(() => {
        if (!mountRef.current) return undefined;

        const map = createMap(mountRef.current, { scrollWheelZoom: false });
        layersRef.current = createBaseLayers();
        layersRef.current.map.addTo(map);
        mapRef.current = map;

        const startLat = lat ?? DEFAULT_CENTER.lat;
        const startLng = lng ?? DEFAULT_CENTER.lng;
        map.setView([startLat, startLng], hasPin ? 16 : DEFAULT_CENTER.zoom);
        if (hasPin) placeMarker(startLat, startLng);

        map.on('click', (event) => {
            const nextLat = Number(event.latlng.lat.toFixed(7));
            const nextLng = Number(event.latlng.lng.toFixed(7));
            placeMarker(nextLat, nextLng);
            setGeoError(null);
            onChangeRef.current?.({ latitude: nextLat, longitude: nextLng });
        });

        const resize = new ResizeObserver(() => map.invalidateSize());
        resize.observe(mountRef.current);

        return () => {
            resize.disconnect();
            map.remove();
            mapRef.current = null;
            markerRef.current = null;
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    // Keep the pin in sync when the values change from outside (e.g. geolocation).
    useEffect(() => {
        if (!mapRef.current || !hasPin) return;
        placeMarker(lat, lng);
        mapRef.current.setView([lat, lng], Math.max(mapRef.current.getZoom(), 16));
    }, [lat, lng, hasPin]);

    // Swap the visible base layer when the Map/Satellite toggle changes.
    useEffect(() => {
        const map = mapRef.current;
        const layers = layersRef.current;
        if (!map || !layers) return;
        Object.entries(layers).forEach(([key, layer]) => {
            if (key === style) layer.addTo(map);
            else map.removeLayer(layer);
        });
    }, [style]);

    const useMyLocation = () => {
        if (!navigator.geolocation) {
            setGeoError('Your browser cannot share a location. Tap the map to drop the pin instead.');
            return;
        }
        setLocating(true);
        setGeoError(null);
        navigator.geolocation.getCurrentPosition(
            (position) => {
                setLocating(false);
                const nextLat = Number(position.coords.latitude.toFixed(7));
                const nextLng = Number(position.coords.longitude.toFixed(7));
                onChangeRef.current?.({ latitude: nextLat, longitude: nextLng });
            },
            () => {
                setLocating(false);
                setGeoError('We could not read your location. Tap the map to drop the pin instead.');
            },
            { enableHighAccuracy: true, timeout: 10000 },
        );
    };

    return (
        <div>
            <div className="flex flex-wrap items-center justify-between gap-2">
                <p className="text-xs text-muted-foreground">
                    Tap the map to set the exact spot. This precise location is required and stays private — tenants only see an approximate area until you accept their viewing.
                </p>
                <button
                    type="button"
                    onClick={useMyLocation}
                    disabled={locating}
                    className="inline-flex h-9 shrink-0 items-center gap-1.5 rounded-xl border border-border bg-muted/40 px-3 text-xs font-bold text-foreground transition hover:bg-muted disabled:opacity-60"
                >
                    <LocateFixed className="h-3.5 w-3.5" /> {locating ? 'Locating…' : 'Use my current location'}
                </button>
            </div>

            <div className="dz-map relative isolate mt-3 h-72 w-full overflow-hidden rounded-2xl border border-border">
                <div ref={mountRef} className="h-full w-full" aria-label="Set the property location on the map" />
                <MapStyleToggle value={style} onChange={setStyle} />
            </div>

            <p className="mt-2 flex items-center gap-1.5 text-xs font-semibold text-muted-foreground">
                <MapPin className="h-3.5 w-3.5 text-emerald-500" />
                {hasPin ? `Pinned at ${lat.toFixed(5)}, ${lng.toFixed(5)}` : 'No location set yet'}
            </p>
            {geoError && <p className="mt-1 text-xs font-medium text-amber-600">{geoError}</p>}
            {error && <p className="mt-1 text-xs font-medium text-rose-600">{error}</p>}
        </div>
    );
}

LocationPicker.propTypes = {
    latitude: PropTypes.oneOfType([PropTypes.string, PropTypes.number]),
    longitude: PropTypes.oneOfType([PropTypes.string, PropTypes.number]),
    onChange: PropTypes.func.isRequired,
    error: PropTypes.string,
};
