import { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { api } from '../api/client';

export default function LoginPage() {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState('');
  const navigate = useNavigate();

  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError('');
    try {
      const res = await api.login(email, password);
      navigate(res.redirect.startsWith('/') ? res.redirect : '/');
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Login failed');
    }
  };

  return (
    <div className="min-h-screen subtle-gradient-background flex items-center justify-center p-4">
      <div className="w-full max-w-md bg-white dark:bg-slate-800 rounded-3xl border border-slate-200/80 dark:border-slate-700/80 p-8 shadow-xl">
        <h1 className="text-2xl font-extrabold mb-2">Welcome back</h1>
        <p className="text-slate-500 mb-6">Sign in to your Dazu Hub account</p>
        <form onSubmit={submit} className="space-y-4">
          {error && <div className="text-red-600 text-sm bg-red-50 p-3 rounded-lg">{error}</div>}
          <div>
            <label className="block text-sm font-semibold mb-1">Email</label>
            <input type="email" required value={email} onChange={(e) => setEmail(e.target.value)} className="w-full px-4 py-3 rounded-xl input-3d bg-slate-50 dark:bg-slate-700" />
          </div>
          <div>
            <label className="block text-sm font-semibold mb-1">Password</label>
            <input type="password" required value={password} onChange={(e) => setPassword(e.target.value)} className="w-full px-4 py-3 rounded-xl input-3d bg-slate-50 dark:bg-slate-700" />
          </div>
          <button type="submit" className="w-full text-white font-bold py-3 rounded-xl" style={{ background: 'var(--gradient)' }}>
            Sign in
          </button>
        </form>
        <p className="text-center mt-4 text-sm"><Link to="/" className="text-emerald-600">← Back to website</Link></p>
      </div>
    </div>
  );
}
