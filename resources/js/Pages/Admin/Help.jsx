import AdminLayout from '@/Layouts/AdminLayout';
import GuideViewer from '@/Components/Help/GuideViewer';

export default function AdminHelp({ guide }) {
    return (
        <AdminLayout title="Admin Guide">
            <div className="mb-10 max-w-3xl">
                <h1 className="text-3xl font-semibold tracking-tight text-slate-900">{guide.title}</h1>
                <p className="mt-2 text-[15px] leading-7 text-slate-600">{guide.tagline}</p>
            </div>
            <GuideViewer guide={guide} stickyOffset="top-20" />
        </AdminLayout>
    );
}
