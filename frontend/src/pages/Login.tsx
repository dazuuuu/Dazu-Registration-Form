import { useState } from 'react';
import { useRouter } from '../router';
import { api } from '../api/client';
import { User, Lock, ShieldAlert, ShieldCheck } from 'lucide-react';

export default function Login() {
  const { navigate } = useRouter();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');

  const handleSignIn = async (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);
    setError('');
    try {
      const res = await api.login(email.trim(), password);
      const role = res.user.role;
      if (role === 'admin' || role === 'superadmin' || res.redirect.includes('admin')) {
        window.location.href = res.redirect;
        return;
      }
      if (role === 'tutor' || res.redirect.includes('tutor')) {
        navigate('/tutor');
        return;
      }
      navigate('/student');
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Login failed');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="bg-slate-50 min-h-screen py-16 text-slate-900 flex items-center justify-center font-sans">
      <div className="max-w-md w-full mx-auto px-4">
        <div className="bg-white border border-slate-200 rounded-2xl p-8 md:p-10 space-y-8 shadow-sm">
          <div className="space-y-2 text-center">
            <span className="text-green-600 text-[10px] uppercase tracking-widest font-extrabold px-2.5 py-1 bg-green-50 border border-green-200 rounded-full">
              Dazu Hub Portal
            </span>
            <h2 className="text-2xl font-extrabold text-slate-900 tracking-tight pt-2">Sign In</h2>
            <p className="text-xs text-slate-400 font-semibold">
              Students and tutors use this portal. Admins are redirected to the PHP admin dashboard.
            </p>
          </div>

          <form onSubmit={handleSignIn} className="space-y-6">
            {error && (
              <div className="p-4 border border-red-200 bg-red-50 text-red-700 text-xs font-semibold rounded-xl flex items-start gap-2">
                <ShieldAlert className="h-4 w-4 shrink-0 mt-0.5" />
                <span>{error}</span>
              </div>
            )}

            <div className="space-y-2">
              <label className="block text-xs font-bold uppercase tracking-widest text-slate-500">Email</label>
              <div className="relative">
                <User className="absolute left-3.5 top-3.5 h-4 w-4 text-slate-400" />
                <input
                  type="email"
                  value={email}
                  onChange={(e) => setEmail(e.target.value)}
                  className="w-full pl-11 pr-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-green-600"
                  required
                />
              </div>
            </div>

            <div className="space-y-2">
              <label className="block text-xs font-bold uppercase tracking-widest text-slate-500">Password</label>
              <div className="relative">
                <Lock className="absolute left-3.5 top-3.5 h-4 w-4 text-slate-400" />
                <input
                  type="password"
                  value={password}
                  onChange={(e) => setPassword(e.target.value)}
                  className="w-full pl-11 pr-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-green-600"
                  required
                />
              </div>
            </div>

            <button
              type="submit"
              disabled={loading}
              className="w-full py-3.5 text-xs font-extrabold uppercase tracking-widest text-white bg-[#15A24A] hover:bg-[#128a3e] rounded-xl disabled:opacity-60"
            >
              {loading ? 'Signing in…' : 'Sign In'}
            </button>
          </form>

          <div className="text-center space-y-3 text-xs text-slate-500">
            <button type="button" onClick={() => navigate('/apply')} className="text-green-600 font-bold hover:underline">
              New student? Apply here
            </button>
            <p>
              <a href="../admin/portal.php" className="text-slate-600 hover:text-green-600 font-semibold inline-flex items-center gap-1">
                <ShieldCheck className="h-3.5 w-3.5" />
                Admin login
              </a>
            </p>
            <button type="button" onClick={() => navigate('/')} className="block w-full text-slate-400 hover:text-slate-600">
              ← Back to website
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}
