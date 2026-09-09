import { useState, useEffect, useRef, useMemo } from 'react';

export default function SearchableSelect({
    value,
    onChange,
    onSearch,
    placeholder = 'Search...',
    className = '',
    options = [],
    loading = false,
    labelKey = 'label',
    valueKey = 'id',
}) {
    const [isOpen, setIsOpen] = useState(false);
    const [searchTerm, setSearchTerm] = useState('');
    const wrapperRef = useRef(null);

    // Filter options locally based on search term
    const filteredOptions = useMemo(() => {
        if (!searchTerm) return options;
        const term = searchTerm.toLowerCase();
        return options.filter(opt =>
            String(opt[labelKey] || '').toLowerCase().includes(term) ||
            String(opt[valueKey] || '').toLowerCase().includes(term)
        );
    }, [options, searchTerm, labelKey, valueKey]);

    useEffect(() => {
        const handleClickOutside = (event) => {
            if (wrapperRef.current && !wrapperRef.current.contains(event.target)) {
                setIsOpen(false);
            }
        };
        document.addEventListener('mousedown', handleClickOutside);
        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, [wrapperRef]);

    useEffect(() => {
        if (!isOpen) {
            setSearchTerm('');
        }
    }, [isOpen]);

    const handleSearchChange = (e) => {
        const term = e.target.value;
        setSearchTerm(term);
        if (onSearch) {
            onSearch(term);
        }
    };

    const handleSelect = (option) => {
        onChange(option[valueKey], option);
        setIsOpen(false);
    };

    const selectedOption = options.find(opt => opt[valueKey] == value);
    const displayLabel = selectedOption ? selectedOption[labelKey] : placeholder;

    return (
        <div className={`relative ${className}`} ref={wrapperRef}>
            <button
                type="button"
                onClick={() => setIsOpen(!isOpen)}
                className="w-full px-3 py-2 text-left border border-gray-300 rounded-lg text-sm bg-white focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent transition-all overflow-hidden text-ellipsis whitespace-nowrap min-h-[38px] flex items-center justify-between"
            >
                <span className={selectedOption ? 'text-gray-900' : 'text-gray-400'}>
                    {displayLabel}
                </span>
                <svg className={`w-4 h-4 text-gray-400 transition-transform ${isOpen ? 'rotate-180' : ''}`} fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 9l-7 7-7-7" />
                </svg>
            </button>

            {isOpen && (
                <div className="absolute z-[100] w-full mt-1 bg-white border border-gray-200 rounded-lg shadow-xl overflow-hidden animate-in fade-in zoom-in duration-200 min-w-[250px]">
                    <div className="p-2 border-b border-gray-100 bg-gray-50">
                        <input
                            autoFocus
                            type="text"
                            value={searchTerm}
                            onChange={handleSearchChange}
                            className="w-full px-3 py-1.5 text-sm border border-gray-200 rounded-md focus:outline-none focus:ring-2 focus:ring-purple-500"
                            placeholder="Type to filter..."
                        />
                    </div>
                    <div className="max-h-60 overflow-y-auto">
                        {loading && searchTerm.length >= 2 ? (
                            <div className="px-4 py-3 text-sm text-gray-500 text-center flex items-center justify-center">
                                <svg className="animate-spin -ml-1 mr-3 h-4 w-4 text-purple-600" fill="none" viewBox="0 0 24 24">
                                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                                    <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                Searching...
                            </div>
                        ) : (
                            <>
                                {filteredOptions.length === 0 ? (
                                    <div className="px-4 py-3 text-sm text-gray-500 text-center">No results found</div>
                                ) : (
                                    filteredOptions.map((option) => (
                                        <button
                                            key={option[valueKey]}
                                            type="button"
                                            onClick={() => handleSelect(option)}
                                            className={`w-full px-4 py-2 text-left text-sm hover:bg-purple-50 transition-colors border-b border-gray-50 last:border-none ${value == option[valueKey] ? 'bg-purple-100 text-purple-900 font-medium' : 'text-gray-700'
                                                }`}
                                        >
                                            <div className="flex flex-col">
                                                <span>{option[labelKey]}</span>
                                                {option.subLabel && <span className="text-xs text-gray-400">{option.subLabel}</span>}
                                            </div>
                                        </button>
                                    ))
                                )}
                            </>
                        )}
                    </div>
                </div>
            )}
        </div>
    );
}

