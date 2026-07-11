import { useEffect, useState } from 'react';
import { api, type Course, type FeeQuote, type PaymentCredentials } from '../api/client';

const fmt = (n: number) => `KSh ${Math.round(n).toLocaleString()}`;

export default function ApplyForm({ presetCourseId = 0, onSuccess }: { presetCourseId?: number; onSuccess?: () => void }) {
  const [courses, setCourses] = useState<Course[]>([]);
  const [pay, setPay] = useState<PaymentCredentials | null>(null);
  const [quote, setQuote] = useState<FeeQuote | null>(null);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [loading, setLoading] = useState(false);
  const [form, setForm] = useState({
    full_name: '', email: '', phone: '', course_id: presetCourseId || 0,
    study_mode: 'online', payment_plan: 'monthly', payment_reference: '',
  });

  useEffect(() => {
    api.courses().then((r) => setCourses(r.data)).catch(() => {});
    api.paymentSettings().then((r) => setPay(r.data)).catch(() => {});
  }, []);

  useEffect(() => {
    if (presetCourseId) setForm((f) => ({ ...f, course_id: presetCourseId }));
  }, [presetCourseId]);

  useEffect(() => {
    if (!form.course_id) return;
    api.calculateFee({
      course_id: form.course_id,
      study_mode: form.study_mode,
      payment_plan: form.payment_plan,
    }).then((r) => setQuote(r.data)).catch(() => setQuote(null));
  }, [form.course_id, form.study_mode, form.payment_plan]);

  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError('');
    setSuccess('');
    setLoading(true);
    const fd = new FormData(e.target as HTMLFormElement);
    fd.set('course_id', String(form.course_id));
    fd.set('study_mode', form.study_mode);
    fd.set('payment_plan', form.payment_plan);
    try {
      const res = await api.submitRegistration(fd);
      setSuccess(res.message);
      onSuccess?.();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Submission failed');
    } finally {
      setLoading(false);
    }
  };

  if (success) {
    return <div className="alert alert-success"><strong>Thank you!</strong> {success}</div>;
  }

  return (
    <form className="form-card" onSubmit={submit}>
      {error && <div className="alert alert-error">{error}</div>}
      <div className="form-row">
        <div className="field">
          <label>Full Name *</label>
          <input name="full_name" required value={form.full_name} onChange={(e) => setForm({ ...form, full_name: e.target.value })} />
        </div>
        <div className="field">
          <label>Phone *</label>
          <input name="phone" required value={form.phone} onChange={(e) => setForm({ ...form, phone: e.target.value })} />
        </div>
      </div>
      <div className="field">
        <label>Email *</label>
        <input type="email" name="email" required value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} />
      </div>
      <div className="field">
        <label>Course *</label>
        <select required value={form.course_id} onChange={(e) => setForm({ ...form, course_id: Number(e.target.value) })}>
          <option value={0}>Select course</option>
          {courses.map((c) => <option key={c.id} value={c.id}>{c.title} — {fmt(c.course_fee)}</option>)}
        </select>
      </div>
      <div className="form-row">
        <div className="field">
          <label>Study mode</label>
          <select value={form.study_mode} onChange={(e) => setForm({ ...form, study_mode: e.target.value })}>
            <option value="online">Online</option>
            <option value="physical">Physical</option>
          </select>
        </div>
        <div className="field">
          <label>Payment plan</label>
          <select value={form.payment_plan} onChange={(e) => setForm({ ...form, payment_plan: e.target.value })}>
            <option value="monthly">Monthly</option>
            <option value="3months">3-Month</option>
            <option value="full">Full</option>
          </select>
        </div>
      </div>
      {quote && (
        <div className="pay-box" style={{ marginBottom: 6 }}>
          <div>Reg. fee: <strong>{fmt(quote.registration_fee)}</strong></div>
          <div>Installment: <strong>{fmt(quote.installment_amount)}</strong></div>
          <div>Pay now: <strong>{fmt(quote.total_due_now)}</strong></div>
        </div>
      )}
      {pay && (
        <div className="pay-box" style={{ marginBottom: 6 }}>
          <div>{pay.bank} · {pay.service}</div>
          <div>Paybill <strong>{pay.paybill}</strong> · Account <strong>{pay.account_number}</strong></div>
          <div>Account name <strong>{pay.account_name}</strong></div>
        </div>
      )}
      <div className="field">
        <label>M-Pesa name / code used *</label>
        <input name="payment_reference" required value={form.payment_reference} onChange={(e) => setForm({ ...form, payment_reference: e.target.value })} />
      </div>
      <div className="field">
        <label>ID / Result slip (PDF) *</label>
        <input type="file" name="id_document" accept="application/pdf" required />
      </div>
      <button className="btn btn-primary" style={{ width: '100%' }} disabled={loading}>
        {loading ? 'Submitting…' : 'Submit application'}
      </button>
    </form>
  );
}
