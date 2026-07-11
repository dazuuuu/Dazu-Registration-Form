import { BrowserRouter, Navigate, Route, Routes } from 'react-router-dom';
import { RouterProvider } from './router';
import Navbar from './components/Navbar';
import Footer from './components/Footer';
import Home from './pages/Home';
import Courses from './pages/Courses';
import About from './pages/About';
import Projects from './pages/Projects';
import Blog from './pages/Blog';
import Contact from './pages/Contact';
import Apply from './pages/Apply';
import Login from './pages/Login';
import PrivacyPolicy from './pages/PrivacyPolicy';
import TermsOfService from './pages/TermsOfService';
import CourseDetail from './pages/CourseDetail';
import StudentDashboard from './pages/student/StudentDashboard';
import StudentPayments from './pages/student/StudentPayments';
import TutorDashboard from './pages/tutor/TutorDashboard';

function SiteLayout({ children }: { children: React.ReactNode }) {
  return (
    <div className="flex flex-col min-h-screen bg-white">
      <Navbar />
      <main className="flex-1">{children}</main>
      <Footer />
    </div>
  );
}

export default function App() {
  return (
    <BrowserRouter>
      <RouterProvider>
        <Routes>
          <Route path="/" element={<SiteLayout><Home /></SiteLayout>} />
          <Route path="/courses" element={<SiteLayout><Courses /></SiteLayout>} />
          <Route path="/courses/:id" element={<SiteLayout><CourseDetail /></SiteLayout>} />
          <Route path="/about" element={<SiteLayout><About /></SiteLayout>} />
          <Route path="/projects" element={<SiteLayout><Projects /></SiteLayout>} />
          <Route path="/blog" element={<SiteLayout><Blog /></SiteLayout>} />
          <Route path="/contact" element={<SiteLayout><Contact /></SiteLayout>} />
          <Route path="/apply" element={<SiteLayout><Apply /></SiteLayout>} />
          <Route path="/privacy" element={<SiteLayout><PrivacyPolicy /></SiteLayout>} />
          <Route path="/terms" element={<SiteLayout><TermsOfService /></SiteLayout>} />
          <Route path="/login" element={<Login />} />
          <Route path="/student" element={<StudentDashboard />} />
          <Route path="/student/payments" element={<StudentPayments />} />
          <Route path="/tutor" element={<TutorDashboard />} />
          <Route path="*" element={<Navigate to="/" replace />} />
        </Routes>
      </RouterProvider>
    </BrowserRouter>
  );
}
