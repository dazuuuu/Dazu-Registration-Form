import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { api, type TutorDashboard as Dash } from '../../api/client';

export default function TutorDashboard() {
  const [data, setData] = useState<Dash | null>(null);

  useEffect(() => { api.tutorDashboard().then((r) => setData(r.data)).catch(() => {}); }, []);
  if (!data) return <div className="min-h-screen flex items-center justify-center">Loading…</div>;

  return (
    <div className="min-h-screen subtle-gradient-background p-6">
      <div className="max-w-5xl mx-auto">
        <div className="flex justify-between items-center mb-6">
          <h1 className="text-2xl font-extrabold">Tutor Portal</h1>
          <Link to="/" className="text-emerald-600 font-semibold">← Website</Link>
        </div>
        <p className="text-slate-600 mb-6">Welcome, {data.tutor.full_name}</p>
        <div className="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
          <div className="bg-white dark:bg-slate-800 rounded-2xl p-5 border"><div className="text-sm text-slate-500">Courses</div><div className="text-3xl font-extrabold">{data.stats.courses}</div></div>
          <div className="bg-white dark:bg-slate-800 rounded-2xl p-5 border"><div className="text-sm text-slate-500">Students</div><div className="text-3xl font-extrabold">{data.stats.students}</div></div>
          <div className="bg-white dark:bg-slate-800 rounded-2xl p-5 border"><div className="text-sm text-slate-500">To grade</div><div className="text-3xl font-extrabold">{data.stats.pending}</div></div>
        </div>
        <a href="/tutor/courses/index.php" className="inline-flex text-white font-bold px-5 py-3 rounded-xl" style={{ background: 'var(--gradient)' }}>
          Open curriculum manager
        </a>
      </div>
    </div>
  );
}
