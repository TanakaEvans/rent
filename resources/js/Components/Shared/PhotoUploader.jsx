import { useEffect, useMemo, useRef } from 'react';
import { ImagePlus, Trash2, UploadCloud } from 'lucide-react';
import { cn } from '@/lib/utils';

function entryUrl(entry) {
    return entry instanceof File ? URL.createObjectURL(entry) : entry;
}

function revokeUrl(url) {
    if (url.startsWith('blob:')) URL.revokeObjectURL(url);
}

export default function PhotoUploader({ cover = null, images = [], onChange, error = null }) {
    const coverInput = useRef(null);
    const galleryInput = useRef(null);

    const coverUrl = useMemo(() => (cover ? entryUrl(cover) : null), [cover]);
    const imageUrls = useMemo(() => images.map(entryUrl), [images]);

    useEffect(() => () => {
        if (coverUrl) revokeUrl(coverUrl);
        imageUrls.forEach(revokeUrl);
    }, [coverUrl, imageUrls]);

    const pickCover = (file) => {
        onChange({ cover: file, images });
    };

    const addImages = (fileList) => {
        if (!fileList?.length) return;
        onChange({ cover, images: [...images, ...Array.from(fileList)] });
    };

    const removeImage = (index) => {
        onChange({ cover, images: images.filter((_, i) => i !== index) });
    };

    return (
        <div className="space-y-5">
            <div className="flex flex-wrap items-start gap-4">
                <div className="h-36 w-36 shrink-0 overflow-hidden rounded-2xl border border-dashed border-border bg-muted/50">
                    {coverUrl ? (
                        <img src={coverUrl} alt="Cover preview" className="h-full w-full object-cover" />
                    ) : (
                        <div className="grid h-full w-full place-items-center text-muted-foreground">
                            <ImagePlus className="h-8 w-8" />
                        </div>
                    )}
                </div>
                <div>
                    <p className="text-sm font-bold text-foreground">Cover photo</p>
                    <p className="mt-0.5 max-w-xs text-xs text-muted-foreground">
                        This is the highlight tile tenants see first in search results.
                    </p>
                    <div className="mt-3 flex items-center gap-2">
                        <input
                            ref={coverInput}
                            type="file"
                            accept="image/*"
                            className="hidden"
                            onChange={(e) => {
                                const file = e.target.files?.[0];
                                if (file) pickCover(file);
                                e.target.value = '';
                            }}
                        />
                        <button
                            type="button"
                            onClick={() => coverInput.current?.click()}
                            className="inline-flex h-9 items-center gap-1.5 rounded-full border border-border bg-white px-4 text-xs font-bold text-foreground transition hover:border-primary hover:text-primary"
                        >
                            <UploadCloud className="h-3.5 w-3.5" />
                            {cover ? 'Change cover' : 'Upload cover'}
                        </button>
                        {cover && (
                            <button
                                type="button"
                                onClick={() => onChange({ cover: null, images })}
                                className="inline-flex h-9 items-center gap-1.5 rounded-full border border-rose-200 bg-rose-50 px-4 text-xs font-bold text-rose-600 transition hover:bg-rose-100"
                            >
                                <Trash2 className="h-3.5 w-3.5" /> Remove
                            </button>
                        )}
                    </div>
                </div>
            </div>

            <div>
                <p className="text-sm font-bold text-foreground">Photo gallery <span className="font-medium text-muted-foreground">(up to 8)</span></p>
                {imageUrls.length > 0 ? (
                    <div className="mt-3 grid grid-cols-3 gap-3 sm:grid-cols-4 md:grid-cols-5">
                        {imageUrls.map((url, index) => (
                            <div key={`${url}-${index}`} className="group relative aspect-square overflow-hidden rounded-xl border border-border">
                                <img src={url} alt={`Photo ${index + 1}`} className="h-full w-full object-cover" />
                                <button
                                    type="button"
                                    aria-label={`Remove photo ${index + 1}`}
                                    onClick={() => removeImage(index)}
                                    className="absolute right-1.5 top-1.5 grid h-7 w-7 place-items-center rounded-full bg-black/60 text-white opacity-0 transition group-hover:opacity-100 hover:bg-rose-600"
                                >
                                    <Trash2 className="h-3.5 w-3.5" />
                                </button>
                            </div>
                        ))}
                    </div>
                ) : (
                    <p className="mt-2 text-xs text-muted-foreground">No gallery photos yet. The cover also counts as your single photo.</p>
                )}

                {images.length < 8 && (
                    <>
                        <input
                            ref={galleryInput}
                            type="file"
                            accept="image/*"
                            multiple
                            className="hidden"
                            onChange={(e) => {
                                addImages(e.target.files);
                                e.target.value = '';
                            }}
                        />
                        <button
                            type="button"
                            onClick={() => galleryInput.current?.click()}
                            className="mt-3 inline-flex h-10 items-center gap-2 rounded-full border border-dashed border-border bg-muted/40 px-5 text-xs font-bold text-foreground transition hover:border-primary hover:text-primary"
                        >
                            <ImagePlus className="h-4 w-4" /> Add photos
                        </button>
                    </>
                )}
            </div>

            {error && <p className={cn('rounded-xl border border-rose-200 bg-rose-50 px-3.5 py-2.5 text-xs font-semibold text-rose-700')}>{error}</p>}
        </div>
    );
}