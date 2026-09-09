import { Link } from '@inertiajs/react';

/**
 * Reusable Pagination component for Inertia.js + Laravel pagination
 * 
 * @param {Object} props
 * @param {Object} props.data - Laravel paginator object with links, current_page, last_page, from, to, total
 * @param {string} [props.className] - Additional CSS classes for the container
 * @param {boolean} [props.showInfo=true] - Show "Page X of Y" info
 * @param {boolean} [props.showCount=true] - Show "Showing X - Y of Z" count
 */
export default function Pagination({
    data,
    className = '',
    showInfo = true,
    showCount = true
}) {
    // Don't render if only 1 page
    if (!data || data.last_page <= 1) {
        return null;
    }

    return (
        <div className={`flex flex-col items-center gap-2 mt-4 ${className}`}>
            {/* Record count */}
            {showCount && data.total > 0 && (
                <span className="text-sm text-gray-500">
                    Showing {data.from || 0} - {data.to || 0} of {data.total} records
                </span>
            )}

            {/* Pagination links */}
            <div className="flex justify-center items-center space-x-1">
                {data.links.map((link, i) => {
                    const isDisabled = !link.url;
                    const isActive = link.active;

                    return (
                        <Link
                            key={i}
                            href={link.url || '#'}
                            preserveScroll
                            preserveState
                            className={`
                                px-3 py-1.5 rounded border text-sm font-medium transition-colors
                                ${isActive
                                    ? 'bg-cyan-600 text-white border-cyan-600 shadow-sm'
                                    : 'bg-white text-gray-700 hover:bg-gray-50 border-gray-300'
                                }
                                ${isDisabled
                                    ? 'opacity-50 cursor-not-allowed pointer-events-none'
                                    : 'hover:border-cyan-300'
                                }
                            `}
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    );
                })}
            </div>

            {/* Page info */}
            {showInfo && (
                <span className="text-sm text-gray-500">
                    Page {data.current_page} of {data.last_page}
                </span>
            )}
        </div>
    );
}
