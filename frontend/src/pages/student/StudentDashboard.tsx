import { useEffect, useState } from 'react';
import { Navigate } from 'react-router-dom';
import { api, type StudentDashboard as Dash } from '../../api/client';

export default function StudentDashboardPage() {
  const [data, setData] = useState<Dash | null>(null);
  const [error, setError] = useState('');

  useEffect(() => {
    api.me().then((r) => {
      if (!r.authenticated || r.user?.role !== 'student') setError('Please login as a student');
    });
    api.studentDashboard().then((r) => setData(r.data)).catch((e) => setError(e.message));
  }, []);

  if (error) return <Navigate to="/login" replace />;
  if (!data) return <div>Loading…</div>;

  const r = data.registration;
  return (
    <div>
      <h1>Student dashboard</h1>
      {r.portal_locked && <div className="alert alert-error">Portal locked — submit your installment payment to continue learning.</div>}
      <div className="stat-grid">
        <div className="stat-card"><div className="label">Course</div><div className="value" style={{ fontSize: '1rem' }}>{r.course_title}</div></div>
        <div className="stat-card"><div className="label">Balance</div><div className="value">KSh {r.balance.toLocaleString()}</div></div>
        <div className="stat-card"><div className="label">Paid</div><div className="value">KSh {r.total_paid.toLocaleString()}</div></div>
      </div>
      <div className="panel" style={{ padding: 14 }}>
        <p><strong>Registration:</strong> {r.registration_no}</p>
        <p><strong>Plan:</strong> {r.payment_plan} · <strong>Mode:</strong> {r.study_mode}</p>
        <p><strong>Next due:</strong> KSh {r.next_amount.toLocaleString()}</p>
      </div>
    </div>
  );
}
