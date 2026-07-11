import React, { useState } from 'react';

const Footer: React.FC = () => {
    const [email, setEmail] = useState('');
    const [status, setStatus] = useState<'idle' | 'loading' | 'success' | 'error'>('idle');
    const [message, setMessage] = useState('');

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        if (!email || !/\S+@\S+\.\S+/.test(email)) {
            setStatus('error');
            setMessage('Please enter a valid email.');
            return;
        }
        setStatus('loading');
        setMessage('');

        try {
            const response = await fetch('/api/subscribe', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ email }),
            });
            const data = await response.json();
            if (!response.ok) {
                throw new Error(data.message || 'Subscription failed.');
            }
            setStatus('success');
            setMessage(data.message);
            setEmail('');
        } catch (error) {
            setStatus('error');
            setMessage(error instanceof Error ? error.message : 'Subscription failed.');
        }
    };

    return (
        <footer className="bg-slate-900 dark:bg-black rounded-3xl">
            <div className="max-w-7xl mx-auto py-12 px-6 lg:px-8">
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
                    <div className="md:col-span-2 lg:col-span-1">
                        <h3 className="text-lg font-semibold text-white mb-4">Dazu AI Hub</h3>
                        <p className="text-slate-400 dark:text-slate-500">Empowering Africa with AI through practical training and innovative development services.</p>
                    </div>
                    <div>
                        <h3 className="text-lg font-semibold text-white mb-4">Contact Us</h3>
                        <ul className="space-y-3 text-slate-400 dark:text-slate-500">
                            <li className="flex items-start"><span className="mt-1 mr-2 text-teal-400"><svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" /><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" /></svg></span>Thika CBS, 1st floor room 4</li>
                            <li className="flex items-center"><span className="mr-2 text-teal-400"><svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" /></svg></span>0750116600</li>
                            <li className="flex items-center"><span className="mr-2 text-teal-400"><svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg></span>dazuai01@gmail.com</li>
                        </ul>
                    </div>
                    <div className="md:col-span-2 lg:col-span-2">
                        <h3 className="text-lg font-semibold text-white mb-4">Subscribe to our Newsletter</h3>
                        <p className="text-slate-400 dark:text-slate-500 mb-4">Get the latest AI trends, course updates, and exclusive offers.</p>
                        {status === 'success' ? (
                             <div className="p-4 rounded-lg bg-emerald-500/10 text-emerald-400">
                                <p className="font-semibold">Success!</p>
                                <p>{message}</p>
                            </div>
                        ) : (
                            <form onSubmit={handleSubmit} className="flex flex-col sm:flex-row gap-2">
                                <input 
                                    type="email"
                                    placeholder="Enter your email"
                                    className="flex-1 px-4 py-2 rounded-xl bg-slate-800 dark:bg-slate-900 border border-slate-700 dark:border-slate-600 focus:outline-none focus:ring-2 focus:ring-teal-500 text-white"
                                    value={email}
                                    onChange={(e) => setEmail(e.target.value)}
                                    disabled={status === 'loading'}
                                    aria-label="Email for newsletter"
                                />
                                <button
                                    type="submit"
                                    className="text-white font-bold px-5 py-2 rounded-xl hover:opacity-90 transition-opacity disabled:opacity-70"
                                    style={{background: 'var(--gradient)'}}
                                    disabled={status === 'loading'}
                                >
                                    {status === 'loading' ? '...' : 'Subscribe'}
                                </button>
                            </form>
                        )}
                        {status === 'error' && (
                            <p className="text-red-400 text-sm mt-2" role="alert">{message}</p>
                        )}
                    </div>
                </div>
                <div className="mt-8 pt-8 border-t border-slate-800 dark:border-slate-700 text-center">
                    <p className="text-slate-400 dark:text-slate-500 text-sm">&copy; {new Date().getFullYear()} Dazu AI Hub. All rights reserved.</p>
                </div>
            </div>
        </footer>
    );
};

export default Footer;