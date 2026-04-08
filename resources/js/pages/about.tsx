import { type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';

export default function About() {
    const { auth } = usePage<SharedData>().props;

    return (
        <>
           
            <div className="flex w-full items-center justify-center opacity-100 transition-opacity duration-750 lg:grow starting:opacity-0">
                <main className="flex w-full max-w-[335px] flex-col-reverse lg:max-w-4xl lg:flex-row">
                    About Us
                </main>
            </div>
             
           
        </>
    );
}
