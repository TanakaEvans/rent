import React, { useEffect, useState } from 'react';
import { Head, Link } from '@inertiajs/react';

export default function Unauthorized({ routeDescription }) {
    const [mounted, setMounted] = useState(false);

    useEffect(() => {
        setMounted(true);
    }, []);

    return (
        <>
            <Head title="Access Denied | Value Chain Management" />

            <div className="min-h-screen bg-slate-900 flex items-center justify-center relative overflow-hidden text-white font-sans selection:bg-red-500 selection:text-white">
                {/* Global styles since we're using a specific font */}
                <style>{`
                    @import url('https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap');
                    
                    body {
                        font-family: 'Outfit', sans-serif;
                    }

                    .glass-card {
                        background: rgba(255, 255, 255, 0.05);
                        backdrop-filter: blur(20px);
                        -webkit-backdrop-filter: blur(20px);
                        border: 1px solid rgba(255, 255, 255, 0.1);
                        box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.37);
                    }
                    
                    .ambient-light {
                        position: absolute;
                        border-radius: 50%;
                        filter: blur(80px);
                        opacity: 0.5;
                        z-index: 0;
                    }
                    
                    .animate-float {
                        animation: float 10s infinite alternate;
                    }
                    
                    .animate-float-reverse {
                        animation: float 12s infinite alternate-reverse;
                    }

                    @keyframes float {
                        0% { transform: translate(0, 0); }
                        100% { transform: translate(30px, 30px); }
                    }
                    
                    @keyframes pulse-ring {
                        0% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.4); }
                        70% { box-shadow: 0 0 0 20px rgba(239, 68, 68, 0); }
                        100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
                    }
                `}</style>

                {/* Ambient Background Lights */}
                <div className="ambient-light w-[500px] h-[500px] bg-purple-900/40 -top-24 -left-24 animate-float"></div>
                <div className="ambient-light w-[400px] h-[400px] bg-blue-900/40 -bottom-12 -right-12 animate-float-reverse"></div>

                <div
                    className={`relative z-10 w-full max-w-lg p-6 transition-all duration-1000 ease-out transform ${mounted ? 'translate-y-0 opacity-100' : 'translate-y-12 opacity-0'}`}
                >
                    <div className="glass-card rounded-[32px] p-12 text-center">
                        <span className="block text-xs font-bold tracking-[2px] text-white/40 uppercase mb-8">
                            401 Unauthorized
                        </span>

                        <div className="relative w-20 h-20 mx-auto mb-8 flex items-center justify-center rounded-full bg-red-500/10 border border-red-500/20"
                            style={{ animation: 'pulse-ring 2s infinite' }}>
                            <svg xmlns="http://www.w3.org/2000/svg" className="h-10 w-10 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                            </svg>
                        </div>

                        <h1 className="text-3xl font-bold mb-4 bg-clip-text text-transparent bg-gradient-to-r from-white to-slate-400">
                            Access Restricted
                        </h1>

                        <div className="mb-8">
                            <p className="text-slate-300 text-lg leading-relaxed mb-1">
                                You do not have the required permissions to access this resource.
                            </p>
                            {routeDescription && (
                                <p className="text-sm text-slate-400 mt-2 bg-slate-800/50 py-2 px-4 rounded-lg inline-block border border-slate-700/50">
                                    Attempted Action: <span className="text-white font-medium ml-1">{routeDescription}</span>
                                </p>
                            )}
                        </div>

                        <div className="flex justify-center">
                            <button
                                onClick={() => window.history.back()}
                                className="group relative inline-flex items-center gap-2 px-8 py-3.5 bg-white text-slate-900 rounded-xl font-semibold hover:bg-slate-50 transition-all hover:-translate-y-0.5 hover:shadow-[0_8px_25px_rgba(255,255,255,0.2)]"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="transition-transform group-hover:-translate-x-1">
                                    <path d="M19 12H5M12 19l-7-7 7-7" />
                                </svg>
                                <span>Go Back</span>
                            </button>
                        </div>

                        <div className="mt-12 pt-6 border-t border-white/10 text-[13px] text-slate-500">
                            Contact your administrator if you believe this is a mistake.
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}
