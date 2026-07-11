import { useEffect, useState } from 'react';
import { api, type Course as ApiCourse } from '../api/client';
import { coursesData, type Course } from '../data/courses';
import { apiToClientCourse } from '../lib/courseAdapter';

export function useCourses() {
  const [courses, setCourses] = useState<Course[]>(coursesData);
  const [apiCourses, setApiCourses] = useState<ApiCourse[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    api
      .courses()
      .then((r) => {
        const active = r.data.filter((c) => c.is_active);
        setApiCourses(active);
        if (active.length) {
          setCourses(active.map(apiToClientCourse));
        }
      })
      .catch(() => {
        setApiCourses([]);
      })
      .finally(() => setLoading(false));
  }, []);

  const findApiCourseByTitle = (title: string) =>
    apiCourses.find((c) => c.title.toLowerCase() === title.toLowerCase());

  return { courses, apiCourses, loading, findApiCourseByTitle };
}
