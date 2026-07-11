import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { api, type TutorDashboard as Dash } from '../../api/client';

export default function TutorDashboardPage() {
  const [data, setData] = useState<Dash | null>(null);

  useEffect(() => { api.tutorDashboard().then((r) => setData(r.data)).catch(() => {}); }, []);
  if (!data) return <div>Loading…</div>;

  return (
    <div>
      <h1>Welcome, {data.tutor.full_name}</h1>
      <div className="stat-grid">
        <div className="stat-card"><div className="label">Courses</div><div className="value">{data.stats.courses}</div></div>
        <div className="stat-card"><div className="label">Students</div><div className="value">{data.stats.students}</div></div>
        <div className="stat-card"><div className="label">To grade</div><div className="value">{data.stats.pending}</div></div>
      </div>
      <div className="panel" style={{ padding: 14, marginBottom: 16 }}>
        <h3 style={{ marginTop: 0 }}>My courses</h3>
        {data.tutor.courses.map((c) => (
          <div key={c.id} style={{ padding: '8px 0', borderTop: '1px solid #e2e8f0' }}>{c.title}</div>
        ))}
      </div>
      <a href="/tutor/courses/index.php" className="btn btn-outline">Open full tutor curriculum (PHP)</a>
      {data.stats.pending > 0 && (
        <p style={{ marginTop: 12 }}><Link to="/tutor/submissions">Grade {data.stats.pending} submission(s)</Link></p>
      )}
    </div>
  );
}
