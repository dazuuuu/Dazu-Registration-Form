import {
  Brain,
  ChartColumn,
  Code2,
  Globe,
  ShieldCheck,
  Terminal,
  type LucideIcon,
} from 'lucide-react';
import type { Course as ApiCourse } from '../api/client';
import type { Course } from '../data/courses';

const iconByAbbrev: Record<string, LucideIcon> = {
  WD: Code2,
  CY: ShieldCheck,
  DA: ChartColumn,
  AI: Brain,
  PY: Terminal,
  WEB: Globe,
};

const categoryByAbbrev: Record<string, Course['category']> = {
  WD: 'Software & Web',
  CY: 'Security & Infrastructure',
  DA: 'AI & Data Science',
  AI: 'AI & Data Science',
};

export function apiToClientCourse(c: ApiCourse): Course {
  return {
    id: String(c.id),
    title: c.title,
    description: c.overview || c.description,
    icon: iconByAbbrev[c.abbreviation] || Code2,
    duration: `${c.duration_months} months`,
    level: 'Beginner',
    category: categoryByAbbrev[c.abbreviation] || 'Software & Web',
    price: `KES ${Number(c.course_fee).toLocaleString()}`,
    rating: parseFloat(c.rating || '4.8'),
    ratingCount: 120,
    image:
      c.cover_image ||
      'https://images.unsplash.com/photo-1515879218367-8466d910aaa4?auto=format&fit=crop&w=600&q=80',
    syllabus: c.description ? c.description.split('\n').filter(Boolean).slice(0, 6) : [],
  };
}
