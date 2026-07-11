import { useOutletContext } from 'react-router-dom';
import Courses from '../components/Courses';

export default function CoursesRoute() {
  const { onGetStartedClick } = useOutletContext<{
    onGetStartedClick: (courseName?: string, courseId?: number) => void;
  }>();

  return (
    <Courses
      onCourseSelect={(courseName, courseId) => onGetStartedClick(courseName, courseId)}
    />
  );
}
