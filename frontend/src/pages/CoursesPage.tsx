import { CourseGrid } from '../components/CourseCard';

export default function CoursesPage() {
  return (
    <main className="section container">
      <div className="section-head">
        <h2>Our Courses</h2>
        <p>Choose a program and apply online after M-Pesa payment.</p>
      </div>
      <CourseGrid />
    </main>
  );
}
