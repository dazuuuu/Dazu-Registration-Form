import { useRef, useState } from 'react';
import { Outlet, useLocation, useNavigate } from 'react-router-dom';
import Header from '../components/Header';
import Sidebar from '../components/Sidebar';
import Chatbot from '../components/Chatbot';
import RegistrationModal from '../components/RegistrationModal';
import { pathToView, viewToPath, type ViewType } from '../context/ThemeContext';

export default function ClientShell() {
  const navigate = useNavigate();
  const location = useLocation();
  const activeView = pathToView(location.pathname);
  const [isRegistrationModalOpen, setRegistrationModalOpen] = useState(false);
  const [isMobileSidebarOpen, setMobileSidebarOpen] = useState(false);
  const [selectedCourse, setSelectedCourse] = useState('');
  const [selectedCourseId, setSelectedCourseId] = useState<number | null>(null);
  const mobileMenuButtonRef = useRef<HTMLButtonElement>(null);

  const setActiveView = (view: ViewType) => {
    navigate(viewToPath(view));
  };

  const handleGetStartedClick = (courseName = '', courseId?: number) => {
    setSelectedCourse(courseName);
    setSelectedCourseId(courseId ?? null);
    setRegistrationModalOpen(true);
  };

  return (
    <div className="min-h-screen bg-[#F7F8FA] dark:bg-gray-900 text-slate-800 dark:text-slate-200 font-sans">
      <Header
        activeView={activeView}
        setActiveView={setActiveView}
        onGetStartedClick={() => handleGetStartedClick()}
        setMobileSidebarOpen={setMobileSidebarOpen}
        mobileMenuButtonRef={mobileMenuButtonRef}
      />
      <Sidebar
        activeView={activeView}
        setActiveView={setActiveView}
        onGetStartedClick={() => handleGetStartedClick()}
        isMobileOpen={isMobileSidebarOpen}
        setMobileOpen={setMobileSidebarOpen}
        mobileMenuButtonRef={mobileMenuButtonRef}
      />

      <main id="main-content" className="subtle-gradient-background p-4 sm:p-6 lg:p-8 pt-40 lg:pt-48">
        <div key={location.pathname} className="animate-slideInUp max-w-7xl mx-auto">
          <Outlet context={{ onGetStartedClick: handleGetStartedClick, setSelectedCourse }} />
        </div>
      </main>

      <Chatbot />
      <RegistrationModal
        isOpen={isRegistrationModalOpen}
        onClose={() => setRegistrationModalOpen(false)}
        initialCourse={selectedCourse}
        initialCourseId={selectedCourseId}
      />
    </div>
  );
}
