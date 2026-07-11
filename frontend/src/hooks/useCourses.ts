import { useEffect, useState } from 'react';
import { api, type Course as ApiCourse } from '../api/client';
import type { Course } from '../data/courses';
import { apiToClientCourse } from '../lib/courseAdapter';

export function useCourses() {
  const [courses, setCourses] = useState<Course[]>([]);
  const [apiCourses, setApiCourses] = useState<ApiCourse[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    api
      .courses()
      .then((r) => {
        const active = r.data.filter((c) => c.is_active && c.id > 0);
        setApiCourses(active);
        setCourses(active.map(apiToClientCourse));
        if (!active.length) {
          setError('No active courses in the database. Add courses in the admin panel.');
        }
      })
      .catch((e) => {
        setError(e instanceof Error ? e.message : 'Could not load courses from server');
        setCourses([]);
        setApiCourses([]);
      })
      .finally(() => setLoading(false));
  }, []);

  const findApiCourseByTitle = (title: string) =>
    apiCourses.find((c) => c.title.toLowerCase() === title.toLowerCase());

  const findApiCourseById = (id: number) => apiCourses.find((c) => c.id === id);

  return { courses, apiCourses, loading, error, findApiCourseByTitle, findApiCourseById };
}
