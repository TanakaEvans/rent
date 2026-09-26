import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import MainLayout from '@/Layouts/MainLayout';
import PropertyForm from './PropertyForm';

export default function PropertyCreate({ auth, amenityOptions }) {
    return (
        <MainLayout title="List a New Property" auth={auth}>
            <Head title="List a New Property" />

            <div className="mx-auto max-w-5xl">
                <div className="mb-6">
                    <Link href={route('owner.properties.index')} className="mb-2 inline-flex items-center gap-1.5 text-sm font-semibold text-primary hover:text-primary/80">
                        <ArrowLeft className="h-4 w-4" /> Back to My Properties
                    </Link>
                    <h2 className="text-balance text-2xl font-extrabold tracking-tight sm:text-3xl">List a New Property</h2>
                    <p className="mt-1 text-sm text-muted-foreground">Answer a few guided steps — every field is an option, so your listing formats cleanly on the marketplace.</p>
                </div>

                <PropertyForm mode="create" amenityOptions={amenityOptions} />
            </div>
        </MainLayout>
    );
}