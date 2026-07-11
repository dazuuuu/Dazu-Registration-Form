import { useEffect, useState } from 'react';
import { Link, useNavigate, useOutletContext, useParams } from 'react-router-dom';
import { api, type Course, type Testimonial } from '../api/client';

const stock: Record<string, string> = {
  WD: 'https://images.unsplash.com/photo-1498050108023-c5249f4df085?w=800&q=80',
  CY: 'https://images.unsplash.com/photo-1550751827-4bd374c3f58b?w=800&q=80',
  DA: 'https://images.unsplash.com/photo-1551288049-bebda4e38f71?w=800&q=80',
  AI: 'https://images.unsplash.com/photo-1677442136019-21780ecad995?w=800&q=80',
};

export default function CourseDetail() {
  const { id } = useParams();
  const navigate = useNavigate();
  const { onGetStartedClick } = useOutletContext<{
    onGetStartedClick: (courseName?: string, courseId?: number) => void;
  }>();
  const [course, setCourse] = useState<Course | null>(null);
  const [reviews, setReviews] = useState<Testimonial[]>([]);

  useEffect(() => {
    const cid = Number(id);
    if (!cid) return;
    api.course(cid).then((r) => setCourse(r.data)).catch(() => setCourse(null));
    api.testimonials('?type=text&limit=6').then((r) => setReviews(r.data)).catch(() => {});
  }, [id]);

  if (!course) {
    return <div className="text-center py-16 text-slate-500">Loading course…</div>;
  }

  const img = course.cover_image || stock[course.abbreviation] || stock.WD;

  return (
    <div>
      <button type="button" onClick={() => navigate('/courses')} className="text-slate-500 hover:text-emerald-600 mb-4">
        ← Back to courses
      </button>
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-8 items-start">
        <div>
          <img src={img} alt={course.title} className="rounded-3xl w-full h-72 object-cover mb-6" />
          <h1 className="text-3xl md:text-4xl font-extrabold text-slate-900 dark:text-slate-100 mb-3">{course.title}</h1>
          <p className="text-slate-600 dark:text-slate-400 text-lg mb-4">{course.overview}</p>
          <div className="flex flex-wrap gap-4 text-sm font-semibold text-slate-700 dark:text-slate-300 mb-6">
            <span>KES {course.course_fee.toLocaleString()}</span>
            <span>{course.duration_months} months</span>
            <span>Rating {course.rating || '4.8'}</span>
          </div>
          <div className="bg-white dark:bg-slate-800 rounded-3xl border border-slate-200/80 dark:border-slate-700/80 p-6">
            <h2 className="text-xl font-bold mb-3">Course overview</h2>
            <p className="text-slate-600 dark:text-slate-400 whitespace-pre-wrap">{course.description || course.overview}</p>
          </div>
          <div className="mt-8">
            <h2 className="text-2xl font-bold mb-4">Student reviews</h2>
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              {reviews.map((t) => (
                <article key={t.id} className="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200/80 dark:border-slate-700/80 p-4">
                  <strong>{t.customer_name}</strong>
                  <p className="text-sm text-slate-500">{t.role}</p>
                  <p className="text-slate-600 dark:text-slate-400 mt-2 text-sm">{t.testimonial_text}</p>
                </article>
              ))}
            </div>
          </div>
        </div>
        <div className="bg-white dark:bg-slate-800 rounded-3xl border border-slate-200/80 dark:border-slate-700/80 p-6 sticky top-40">
          <h2 className="text-2xl font-bold mb-2">Ready to enroll?</h2>
          <p className="text-slate-600 dark:text-slate-400 mb-6">Pay via M-Pesa and complete your application.</p>
          <button
            type="button"
            onClick={() => onGetStartedClick(course.title, course.id)}
            className="w-full text-white font-bold px-6 py-4 rounded-xl shadow-lg mb-3"
            style={{ background: 'var(--gradient)' }}
          >
            Apply for this course
          </button>
          <Link to="/login" className="block text-center text-emerald-600 font-semibold">Already a student? Login</Link>
        </div>
      </div>
    </div>
  );
}
