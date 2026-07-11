import React, { useState } from 'react';

const Newsletter: React.FC = () => {
    const [email, setEmail] = useState('');
    const [status, setStatus] = useState<'idle' | 'loading' | 'success' | 'error'>('idle');
    const [message, setMessage] = useState('');

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        if (!email) {
            setStatus('error');
            setMessage('Please enter an email address.');
            return;
        }
        setStatus('loading');
        setMessage('');

        try {
            const response = await fetch('/api/subscribe', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ email }),
            });

            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.message || 'Something went wrong.');
            }
            
            setStatus('success');
            setMessage(data.message);
            setEmail('');
        } catch (error) {
            setStatus('error');
            const errorMessage = error instanceof Error ? error.message : 'Could not subscribe. Please try again.';
            setMessage(errorMessage);
        }
    };

  return (
    <div>
        <div className="mb-12 text-center">
            <h1 className="text-4xl md:text-5xl font-extrabold text-slate-900 dark:text-slate-100 mb-3">
                Stay Ahead with <span className="gradient-text">AI Insights</span>
            </h1>
            <p className="mt-2 text-lg text-slate-600 dark:text-slate-400 max-w-2xl mx-auto">
                Subscribe to our newsletter for the latest AI trends, course updates, and exclusive offers delivered to your inbox.
            </p>
        </div>
        <div className="max-w-2xl mx-auto bg-white dark:bg-slate-800 p-8 sm:p-10 rounded-3xl shadow-lg border border-slate-200/80 dark:border-slate-700/80">
            {status === 'success' ? (
                <div className="text-center">
                    <h3 className="text-2xl font-bold text-green-600 dark:text-green-400 mb-2">Subscribed!</h3>
                    <p className="text-slate-600 dark:text-slate-400">{message}</p>
                </div>
            ) : (
                <>
                    <form onSubmit={handleSubmit} className="flex flex-col sm:flex-row gap-4">
                        <input
                            type="email"
                            placeholder="Enter your email address"
                            aria-label="Email address"
                            className="flex-1 px-5 py-3 rounded-full w-full text-base bg-slate-50 dark:bg-slate-700 input-3d"
                            required
                            value={email}
                            onChange={(e) => setEmail(e.target.value)}
                            disabled={status === 'loading'}
                        />
                        <button
                            type="submit"
                            className="text-white font-bold px-8 py-3 rounded-full hover:opacity-90 transition-opacity duration-300 shadow-lg text-base flex items-center justify-center disabled:opacity-70"
                            style={{ background: 'var(--gradient)' }}
                            disabled={status === 'loading'}
                        >
                            {status === 'loading' ? 'Subscribing...' : 'Subscribe'}
                        </button>
                    </form>
                    {status === 'error' && (
                        <p className="text-red-500 text-sm mt-4 text-center" role="alert">{message}</p>
                    )}
                </>
            )}
        </div>
    </div>
  );
};

export default Newsletter;