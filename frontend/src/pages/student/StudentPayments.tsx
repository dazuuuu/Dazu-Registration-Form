import { useEffect, useState } from 'react';
import { api, type PaymentRow } from '../../api/client';

export default function StudentPaymentsPage() {
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
      setMsg('Payment submitted for admin verification.');
      setForm({ mpesa_code: '', payer_reference: '' });
      load();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed');
    }
  };

  return (
    <div>
      <h1>Payments</h1>
      {msg && <div className="alert alert-success">{msg}</div>}
      {error && <div className="alert alert-error">{error}</div>}
      <form className="form-card" onSubmit={submit} style={{ marginBottom: 16 }}>
        <div className="field"><label>M-Pesa code</label><input required value={form.mpesa_code} onChange={(e) => setForm({ ...form, mpesa_code: e.target.value })} /></div>
        <div className="field"><label>Payer reference</label><input value={form.payer_reference} onChange={(e) => setForm({ ...form, payer_reference: e.target.value })} /></div>
        <button className="btn btn-primary">Submit payment</button>
      </form>
      <div className="panel" style={{ padding: 0, overflow: 'hidden' }}>
        <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: '.88rem' }}>
          <thead><tr style={{ background: '#f8fafc' }}><th style={{ padding: 10, textAlign: 'left' }}>Amount</th><th>Status</th><th>Code</th><th>Date</th></tr></thead>
          <tbody>
            {rows.map((p) => (
              <tr key={p.id} style={{ borderTop: '1px solid #e2e8f0' }}>
                <td style={{ padding: 10 }}>KSh {Number(p.amount).toLocaleString()}</td>
                <td>{p.status}</td>
                <td>{p.mpesa_code || '—'}</td>
                <td>{p.created_at || '—'}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}
