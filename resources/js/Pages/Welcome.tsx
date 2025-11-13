import { Head } from '@inertiajs/react';
import { Files } from "@/Pages/Files/Files.tsx";

export default function Welcome() {
    return (
        <>
            <Head title="Welcome" />
            <div className="bg-gray-50 bg-slate-900 text-slate-300  text-black/50 dark:bg-black dark:text-white/50">

                <div className="relative flex min-h-screen flex-col items-center justify-center selection:bg-[#FF2D20] selection:text-white">
                    <div className="relative w-full max-w-2xl px-6 lg:max-w-7xl">
                        <main className="mt-6">
                            <div className="grid lg:grid-cols-1">
                                <Files />
                            </div>
                        </main>

                        <footer className="py-16 text-center text-sm text-black dark:text-white/70">
                              2025 | All rights reserved
                        </footer>
                    </div>
                </div>
            </div>
        </>
    );
}
