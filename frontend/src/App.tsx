import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { PublicLayout, PortalLayout } from './components/Layout';
import HomePage from './pages/HomePage';
import CoursesPage from './pages/CoursesPage';
import CourseDetailPage from './pages/CourseDetailPage';
import TestimonialsPage from './pages/TestimonialsPage';
import ApplyPage from './pages/ApplyPage';
import LoginPage from './pages/LoginPage';
import AboutPage from './pages/AboutPage';
import ContactPage from './pages/ContactPage';
import StudentDashboardPage from './pages/student/StudentDashboard';
import StudentPaymentsPage from './pages/student/StudentPayments';
import TutorDashboardPage from './pages/tutor/TutorDashboard';

export default function App() {
  return (
    <BrowserRouter>
      <Routes>
        <Route element={<PublicLayout />}>
          <Route index element={<HomePage />} />
          <Route path="courses" element={<CoursesPage />} />
          <Route path="courses/:id" element={<CourseDetailPage />} />
          <Route path="testimonials" element={<TestimonialsPage />} />
          <Route path="apply" element={<ApplyPage />} />
          <Route path="login" element={<LoginPage />} />
          <Route path="about" element={<AboutPage />} />
          <Route path="contact" element={<ContactPage />} />
        </Route>
        <Route path="/student" element={<PortalLayout base="/student" links={[{ to: '', label: 'Dashboard' }, { to: '/payments', label: 'Payments' }]} />}>
          <Route index element={<StudentDashboardPage />} />
          <Route path="payments" element={<StudentPaymentsPage />} />
        </Route>
        <Route path="/tutor" element={<PortalLayout base="/tutor" links={[{ to: '', label: 'Dashboard' }]} />}>
          <Route index element={<TutorDashboardPage />} />
        </Route>
        <Route path="*" element={<Navigate to="/" replace />} />
      </Routes>
    </BrowserRouter>
  );
}
