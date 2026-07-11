import { useEffect, useState } from 'react';
import { useParams } from 'react-router-dom';
import { useRouter } from '../router';
import { api, type Course } from '../api/client';
import { ArrowLeft, ArrowRight, Star, Clock } from 'lucide-react';

export default function CourseDetail() {
  const { id: routeId } = useParams();
  const { navigate } = useRouter();
  const [course, setCourse] = useState<Course | null>(null);
  const [error, setError] = useState('');

  useEffect(() => {
    const params = new URLSearchParams(window.location.search);
    const fromQuery = params.get('id');
    const id = Number(routeId || fromQuery);
    if (!id) {
      setError('Course not found');
      return;
    }
    api
      .course(id)
      .then((r) => setCourse(r.data))
      .catch(() => setError('Course not found or unavailable'));
  }, [routeId]);

  if (error) {
    return (
      <div className="min-h-screen bg-white py-20 text-center">
        <p className="text-slate-500 mb-4">{error}</p>
        <button type="button" onClick={() => navigate('/courses')} className="text-green-600 font-bold">
          ← Back to courses
        </button>
      </div>
    );
  }

  if (!course) {
    return <div className="min-h-screen bg-white flex items-center justify-center text-slate-500">Loading course…</div>;
  }

  const img =
    course.cover_image ||
    'https://images.unsplash.com/photo-1515879218367-8466d910aaa4?auto=format&fit=crop&w=800&q=80';

  return (
    <div className="bg-white min-h-screen py-12">
      <div className="max-w-[1280px] mx-auto px-4 md:px-8 lg:px-12">
        <button
          type="button"
          onClick={() => navigate('/courses')}
          className="mb-6 flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-slate-500 hover:text-green-600"
        >
          <ArrowLeft className="h-4 w-4" />
          Back to courses
        </button>

        <div className="grid grid-cols-1 lg:grid-cols-2 gap-10 items-start">
          <img src={img} alt={course.title} className="rounded-2xl w-full h-72 object-cover border border-slate-200" />
          <div>
            <span className="text-green-600 text-xs uppercase tracking-widest font-extrabold px-3 py-1 bg-green-50 border border-green-200 rounded-full">
              {course.abbreviation}
            </span>
            <h1 className="text-3xl md:text-4xl font-extrabold text-slate-900 mt-4 mb-3">{course.title}</h1>
            <p className="text-slate-600 leading-relaxed mb-6">{course.overview}</p>
            <div className="flex flex-wrap gap-4 text-sm font-semibold text-slate-700 mb-6">
              <span className="flex items-center gap-1">
                <Star className="h-4 w-4 text-amber-500 fill-amber-500" />
                {course.rating || '4.8'}
              </span>
              <span className="flex items-center gap-1">
                <Clock className="h-4 w-4 text-green-600" />
                {course.duration_months} months
              </span>
              <span className="text-[#15A24A] font-extrabold">KES {Number(course.course_fee).toLocaleString()}</span>
            </div>
            <button
              type="button"
              onClick={() => navigate(`/apply?course=${encodeURIComponent(course.title)}`)}
              className="px-8 py-3 text-xs font-extrabold uppercase tracking-widest text-white bg-[#15A24A] hover:bg-[#128a3e] rounded-md flex items-center gap-2"
            >
              Apply for this course
              <ArrowRight className="h-4 w-4" />
            </button>
          </div>
        </div>

        {course.description && (
          <div className="mt-12 bg-slate-50 border border-slate-200 rounded-2xl p-8">
            <h2 className="text-xl font-extrabold text-slate-900 mb-4">Course overview</h2>
            <p className="text-slate-600 whitespace-pre-wrap leading-relaxed">{course.description}</p>
          </div>
        )}
      </div>
    </div>
  );
}
