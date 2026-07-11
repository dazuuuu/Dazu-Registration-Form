import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { api, type PaymentRow } from '../../api/client';

export default function StudentPayments() {
  const [rows, setRows] = useState<PaymentRow[]>([]);
  const [msg, setMsg] = useState('');
  const [error, setError] = useState('');
  const [form, setForm] = useState({ mpesa_code: '', payer_reference: '' });

  const load = () => api.studentPayments().then((r) => setRows(r.data)).catch(() => {});
  useEffect(() => { load(); }, []);

  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError(''); setMsg('');
    try {
      await api.submitStudentPayment(form.mpesa_code, form.payer_reference);
      setMsg('Payment submitted for verification.');
      setForm({ mpesa_code: '', payer_reference: '' });
      load();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed');
    }
  };

  return (
    <div className="min-h-screen subtle-gradient-background p-6">
      <div className="max-w-3xl mx-auto">
        <Link to="/student" className="text-emerald-600 font-semibold">← Dashboard</Link>
        <h1 className="text-2xl font-extrabold mt-4 mb-6">Payments</h1>
        {msg && <div className="bg-emerald-50 text-emerald-800 p-3 rounded-lg mb-4">{msg}</div>}
        {error && <div className="bg-red-50 text-red-700 p-3 rounded-lg mb-4">{error}</div>}
        <form onSubmit={submit} className="bg-white dark:bg-slate-800 rounded-2xl p-6 border border-slate-200/80 mb-6 space-y-4">
          <input required placeholder="M-Pesa code" value={form.mpesa_code} onChange={(e) => setForm({ ...form, mpesa_code: e.target.value })} className="w-full px-4 py-3 rounded-xl input-3d" />
          <input placeholder="Payer reference" value={form.payer_reference} onChange={(e) => setForm({ ...form, payer_reference: e.target.value })} className="w-full px-4 py-3 rounded-xl input-3d" />
          <button type="submit" className="text-white font-bold px-5 py-3 rounded-xl" style={{ background: 'var(--gradient)' }}>Submit</button>
        </form>
        <div className="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200/80 overflow-hidden">
          <table className="w-full text-sm">
            <thead className="bg-slate-50 dark:bg-slate-700"><tr><th className="p-3 text-left">Amount</th><th>Status</th><th>Code</th></tr></thead>
            <tbody>
              {rows.map((p) => (
                <tr key={p.id} className="border-t border-slate-200 dark:border-slate-700">
                  <td className="p-3">KES {Number(p.amount).toLocaleString()}</td>
                  <td className="text-center">{p.status}</td>
                  <td className="text-center">{p.mpesa_code || '—'}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
}
