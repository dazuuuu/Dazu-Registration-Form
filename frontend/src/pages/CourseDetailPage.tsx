import { useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { api, type Course, type Testimonial } from '../api/client';
import { courseImage } from '../components/CourseCard';
import ApplyForm from '../components/ApplyForm';

export default function CourseDetailPage() {
  const { id } = useParams();
  const [course, setCourse] = useState<Course | null>(null);
  const [reviews, setReviews] = useState<Testimonial[]>([]);
  const [error, setError] = useState('');

  useEffect(() => {
    const cid = Number(id);
    if (!cid) return;
    api.course(cid).then((r) => setCourse(r.data)).catch((e) => setError(e.message));
    api.testimonials('?type=text&limit=6').then((r) => setReviews(r.data)).catch(() => {});
  }, [id]);

  if (error) return <main className="container section"><div className="alert alert-error">{error}</div></main>;
  if (!course) return <main className="container section">Loading course…</main>;

  return (
    <main className="section container">
      <Link to="/courses" style={{ color: '#64748b', textDecoration: 'none' }}>← Back to courses</Link>
      <div className="grid-2" style={{ marginTop: 16, alignItems: 'start' }}>
        <div>
          <img src={courseImage(course)} alt={course.title} style={{ borderRadius: 12, width: '100%', maxHeight: 360, objectFit: 'cover' }} />
          <h1 style={{ margin: '16px 0 8px' }}>{course.title}</h1>
          <p style={{ color: '#64748b' }}>{course.overview}</p>
          <div style={{ display: 'flex', gap: 16, margin: '12px 0', fontSize: '.9rem' }}>
            <span><strong>Fee:</strong> KSh {course.course_fee.toLocaleString()}</span>
            <span><strong>Duration:</strong> {course.duration_months} months</span>
            <span><strong>Rating:</strong> {course.rating || '4.8'}</span>
          </div>
          <div className="panel" style={{ padding: 14, marginTop: 12 }}>
            <h3 style={{ marginTop: 0 }}>Course details</h3>
            <p style={{ whiteSpace: 'pre-wrap' }}>{course.description || course.overview}</p>
          </div>
          <div className="section" style={{ paddingBottom: 0 }}>
            <h2>Student reviews</h2>
            <div className="grid-2">
              {reviews.map((t) => (
                <article key={t.id} className="testimonial-card" style={{ padding: 12 }}>
                  <strong>{t.customer_name}</strong>
                  <div style={{ color: '#f59e0b', fontSize: '.8rem' }}>{'★'.repeat(t.rating)}</div>
                  <p style={{ fontSize: '.88rem', color: '#475569' }}>{t.testimonial_text}</p>
                </article>
              ))}
            </div>
          </div>
        </div>
        <div>
          <h2>Apply for this course</h2>
          <ApplyForm presetCourseId={course.id} />
        </div>
      </div>
    </main>
  );
}
