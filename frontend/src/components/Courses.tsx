import { useEffect, useState } from 'react';
import { api, type Course } from '../api/client';

const stock: Record<string, string> = {
  WD: 'https://images.unsplash.com/photo-1498050108023-c5249f4df085?w=700&q=80',
  CY: 'https://images.unsplash.com/photo-1550751827-4bd374c3f58b?w=700&q=80',
  DA: 'https://images.unsplash.com/photo-1551288049-bebda4e38f71?w=700&q=80',
  AI: 'https://images.unsplash.com/photo-1677442136019-21780ecad995?w=700&q=80',
};

interface CourseCardItem {
  id: number;
  name: string;
  description: string;
  image: string;
  price: number;
  duration?: string;
}

const CourseCard: React.FC<{
  item: CourseCardItem;
  onSelect: (courseName: string, courseId: number) => void;
  onView: (courseId: number) => void;
}> = ({ item, onSelect, onView }) => (
  <div className="bg-white dark:bg-slate-800 rounded-3xl shadow-sm hover:shadow-xl transition-shadow duration-300 border border-slate-200/80 dark:border-slate-700/80 flex flex-col overflow-hidden card-hover-effect glowing-border-effect">
    <button type="button" className="w-full h-48 overflow-hidden text-left" onClick={() => onView(item.id)}>
      <img src={item.image} alt={item.name} className="w-full h-full object-cover" />
    </button>
    <div className="p-6 flex flex-col flex-grow">
      <h3 className="text-xl font-bold text-slate-900 dark:text-slate-100 mb-3">{item.name}</h3>
      <p className="text-slate-600 dark:text-slate-400 leading-relaxed flex-grow text-base">{item.description}</p>
      <div className="mt-6 pt-4 border-t border-slate-200 dark:border-slate-700">
        <p className="text-lg font-bold text-slate-800 dark:text-slate-200">KES {item.price.toLocaleString()}</p>
        {item.duration && <p className="text-sm text-slate-500">{item.duration}</p>}
      </div>
    </div>
    <div className="p-6 pt-0 flex gap-2">
      <button
        type="button"
        onClick={() => onView(item.id)}
        className="flex-1 font-bold px-4 py-3 rounded-xl border border-slate-300 dark:border-slate-600"
      >
        View details
      </button>
      <button
        type="button"
        onClick={() => onSelect(item.name, item.id)}
        className="flex-1 text-white font-bold px-4 py-3 rounded-xl shadow-lg"
        style={{ background: 'var(--gradient)' }}
      >
        Register
      </button>
    </div>
  </div>
);

const Courses: React.FC<{ onCourseSelect: (courseName: string, courseId?: number) => void }> = ({ onCourseSelect }) => {
  const [courses, setCourses] = useState<CourseCardItem[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    api
      .courses()
      .then((r) => {
        setCourses(
          r.data.map((c: Course) => ({
            id: c.id,
            name: c.title,
            description: c.overview,
            image: c.cover_image || stock[c.abbreviation] || stock.WD,
            price: c.course_fee,
            duration: `${c.duration_months} months`,
          })),
        );
      })
      .catch(() => setCourses([]))
      .finally(() => setLoading(false));
  }, []);

  const onView = (courseId: number) => {
    onCourseSelect('', courseId);
  };

  return (
    <div>
      <div className="mb-12 text-center">
        <h1 className="text-4xl md:text-5xl font-extrabold text-slate-900 dark:text-slate-100 mb-3">
          <span className="gradient-text">Explore</span> Our Training Programs
        </h1>
        <p className="mt-2 text-lg text-slate-600 dark:text-slate-400 max-w-2xl mx-auto">
          Designed for all skill levels — live courses from Dazu Hub Institute.
        </p>
      </div>
      {loading ? (
        <div className="text-center text-slate-500 py-12">Loading courses…</div>
      ) : (
        <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-8">
          {courses.map((course) => (
            <CourseCard key={course.id || course.name} item={course} onSelect={onCourseSelect} onView={onView} />
          ))}
        </div>
      )}
    </div>
  );
};

export default Courses;
