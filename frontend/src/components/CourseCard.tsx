import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { api, type Course } from '../api/client';

const stock: Record<string, string> = {
  WD: 'https://images.unsplash.com/photo-1498050108023-c5249f4df085?w=800&q=80',
  CY: 'https://images.unsplash.com/photo-1550751827-4bd374c3f58b?w=800&q=80',
  DA: 'https://images.unsplash.com/photo-1551288049-bebda4e38f71?w=800&q=80',
  AI: 'https://images.unsplash.com/photo-1677442136019-21780ecad995?w=800&q=80',
};

export function courseImage(c: Course) {
  return c.cover_image || stock[c.abbreviation] || stock.WD;
}

export function CourseCard({ course }: { course: Course }) {
  return (
    <article className="course-card">
      <img src={courseImage(course)} alt={course.title} loading="lazy" />
      <div className="course-card-body">
        <h3>{course.title}</h3>
        <p>{course.overview}</p>
        <div className="course-meta">
          <span><i className="fas fa-star" style={{ color: '#f59e0b' }} /> {course.rating || '4.8'}</span>
          <strong>KSh {course.course_fee.toLocaleString()}</strong>
        </div>
        <Link to={`/courses/${course.id}`} className="btn btn-outline" style={{ width: '100%' }}>View course</Link>
      </div>
    </article>
  );
}

export function CourseGrid({ limit }: { limit?: number }) {
  const [courses, setCourses] = useState<Course[]>([]);
  useEffect(() => { api.courses().then((r) => setCourses(r.data)).catch(() => setCourses([])); }, []);
  const items = limit ? courses.slice(0, limit) : courses;
  return <div className="grid-3">{items.map((c) => <CourseCard key={c.id || c.title} course={c} />)}</div>;
}
