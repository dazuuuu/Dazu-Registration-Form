import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
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
    <main className="section container" style={{ maxWidth: 420 }}>
      <h1>Login</h1>
      <form className="form-card" onSubmit={submit}>
        {error && <div className="alert alert-error">{error}</div>}
        <div className="field"><label>Email</label><input type="email" required value={email} onChange={(e) => setEmail(e.target.value)} /></div>
        <div className="field"><label>Password</label><input type="password" required value={password} onChange={(e) => setPassword(e.target.value)} /></div>
        <button className="btn btn-primary" style={{ width: '100%' }}>Sign in</button>
      </form>
    </main>
  );
}
