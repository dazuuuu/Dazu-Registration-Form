import { BrowserRouter, Navigate, Route, Routes } from 'react-router-dom';
import { ThemeProvider } from './context/ThemeContext';
import ClientShell from './components/ClientShell';
import HomepageRoute from './pages/HomepageRoute';
import CoursesRoute from './pages/CoursesRoute';
import CourseDetail from './components/CourseDetail';
import Services from './components/Services';
import Testimonials from './components/Testimonials';
import Newsletter from './components/Newsletter';
import DazuLive from './components/DazuLive';
import PromptCenter from './components/PromptCenter';
import LoginPage from './pages/LoginPage';
import StudentDashboard from './pages/student/StudentDashboard';
import StudentPayments from './pages/student/StudentPayments';
import TutorDashboard from './pages/tutor/TutorDashboard';

export default function App() {
  return (
    <ThemeProvider>
      <BrowserRouter>
        <Routes>
          <Route element={<ClientShell />}>
            <Route index element={<HomepageRoute />} />
            <Route path="courses" element={<CoursesRoute />} />
            <Route path="courses/:id" element={<CourseDetail />} />
            <Route path="services" element={<Services />} />
            <Route path="prompt-center" element={<PromptCenter />} />
            <Route path="testimonials" element={<Testimonials />} />
            <Route path="ask-dazu" element={<DazuLive />} />
            <Route path="newsletter" element={<Newsletter />} />
          </Route>
          <Route path="/login" element={<LoginPage />} />
          <Route path="/student" element={<StudentDashboard />} />
          <Route path="/student/payments" element={<StudentPayments />} />
          <Route path="/tutor" element={<TutorDashboard />} />
          <Route path="*" element={<Navigate to="/" replace />} />
        </Routes>
      </BrowserRouter>
    </ThemeProvider>
  );
}
