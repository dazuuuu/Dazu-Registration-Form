import { useEffect, useState } from 'react';
import { Link, Navigate } from 'react-router-dom';
import { api, type StudentDashboard as Dash } from '../../api/client';

export default function StudentDashboard() {
  const [data, setData] = useState<Dash | null>(null);
  const [error, setError] = useState('');

  useEffect(() => {
    api.studentDashboard().then((r) => setData(r.data)).catch((e) => setError(e.message));
  }, []);

  if (error) return <Navigate to="/login" replace />;
  if (!data) return <div className="min-h-screen flex items-center justify-center">Loading…</div>;

  const r = data.registration;

  return (
    <div className="min-h-screen subtle-gradient-background p-6">
      <div className="max-w-4xl mx-auto">
        <div className="flex justify-between items-center mb-6">
          <h1 className="text-2xl font-extrabold">Student Portal</h1>
          <Link to="/" className="text-emerald-600 font-semibold">← Website</Link>
        </div>
        {r.portal_locked && (
          <div className="bg-amber-50 border border-amber-200 text-amber-800 p-4 rounded-xl mb-4">
            Portal locked — submit your installment payment to continue.
          </div>
        )}
        <div className="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
          <div className="bg-white dark:bg-slate-800 rounded-2xl p-5 border border-slate-200/80">
            <div className="text-sm text-slate-500">Course</div>
            <div className="font-bold">{r.course_title}</div>
          </div>
          <div className="bg-white dark:bg-slate-800 rounded-2xl p-5 border border-slate-200/80">
            <div className="text-sm text-slate-500">Balance</div>
            <div className="text-2xl font-extrabold">KES {r.balance.toLocaleString()}</div>
          </div>
          <div className="bg-white dark:bg-slate-800 rounded-2xl p-5 border border-slate-200/80">
            <div className="text-sm text-slate-500">Paid</div>
            <div className="text-2xl font-extrabold">KES {r.total_paid.toLocaleString()}</div>
          </div>
        </div>
        <Link to="/student/payments" className="inline-flex text-white font-bold px-5 py-3 rounded-xl" style={{ background: 'var(--gradient)' }}>
          Manage payments
        </Link>
      </div>
    </div>
  );
}
