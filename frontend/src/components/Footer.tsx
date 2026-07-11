import React from 'react';
import { useRouter } from '../router';
import { 
  GraduationCap, 
  Phone, 
  MapPin, 
  Mail, 
  Clock, 
  Facebook, 
  Youtube 
} from 'lucide-react';

export default function Footer() {
  const { navigate } = useRouter();

  const companyLinks = [
    { name: 'About Us', path: '/about' },
    { name: 'Student Projects', path: '/projects' },
    { name: 'Institute Blog', path: '/blog' },
    { name: 'Contact & Support', path: '/contact' },
  ];

  const courseCategories = [
    { name: 'Software Development', path: '/courses' },
    { name: 'Artificial Intelligence', path: '/courses' },
    { name: 'Cybersecurity & Networks', path: '/courses' },
    { name: 'Creative Graphics & Design', path: '/courses' },
  ];

  const resourceLinks = [
    { name: 'Online Portal', path: '/login' },
    { name: 'Application Form', path: '/apply' },
    { name: 'Frequently Asked Questions', path: '/contact' },
    { name: 'Terms of Study', path: '/about' },
  ];

  return (
    <footer className="bg-slate-950 text-slate-400 border-t border-slate-900 pt-16 pb-12">
      <div className="max-w-[1280px] mx-auto px-4 md:px-8 lg:px-12">
        <div className="grid grid-cols-2 md:grid-cols-4 gap-12 mb-16">
          
          {/* Logo & About Info */}
          <div className="space-y-6 col-span-2 md:col-span-1">
            <button 
              onClick={() => navigate('/')}
              className="flex items-center gap-2 group text-left cursor-pointer focus:outline-none"
            >
            <img 
              src="https://i.ibb.co/fzfZFtGc/223025.jpg" 
              alt="Dazu Hub Logo" 
              className="h-12 w-12 rounded-lg object-cover shrink-0 transition-transform duration-300 group-hover:scale-105" 
              referrerPolicy="no-referrer"
            />
              <div className="text-left font-sans leading-none">
                <div className="text-xl font-extrabold text-white tracking-tight flex items-baseline">
                  Dazu<span className="text-[#22C55E] ml-1">hub</span>
                </div>
                <div className="text-[10px] text-slate-400 font-extrabold tracking-tight mt-1">
                  Institute
                </div>
              </div>
            </button>
            <p className="text-sm leading-relaxed text-slate-400">
              Empowering future tech leaders with highly practical, project-based technological education in Thika Town. Transform your potential today.
            </p>
            <div className="flex items-center gap-3">
              <a href="#" className="p-2 bg-slate-900 hover:bg-green-600 hover:text-white rounded-lg transition-colors duration-200" aria-label="Facebook">
                <Facebook className="h-4 w-4" />
              </a>
              <a href="#" className="p-2 bg-slate-900 hover:bg-green-600 hover:text-white rounded-lg transition-colors duration-200 flex items-center justify-center" aria-label="TikTok">
                <svg className="h-4 w-4" fill="currentColor" viewBox="0 0 24 24">
                  <path d="M12.53.02C13.84 0 15.14.01 16.44 0c.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.59-1 .01 2.62.02 5.24 0 7.86-.08 1.93-.74 3.86-2.06 5.23-1.47 1.56-3.66 2.38-5.83 2.19-2.32-.17-4.61-1.51-5.63-3.65-1.15-2.33-.84-5.32.88-7.29 1.34-1.58 3.52-2.38 5.59-2.11v4.01c-1.16-.14-2.4.28-3.13 1.2-.78 1-.77 2.52-.01 3.49.77 1 2.16 1.33 3.25.79.88-.41 1.42-1.32 1.43-2.3V0h3.18z"/>
                </svg>
              </a>
              <a href="#" className="p-2 bg-slate-900 hover:bg-green-600 hover:text-white rounded-lg transition-colors duration-200" aria-label="YouTube">
                <Youtube className="h-4 w-4" />
              </a>
            </div>
          </div>

          {/* Quick Links */}
          <div className="space-y-6">
            <h4 className="text-sm uppercase tracking-widest font-bold text-white">
              Company
            </h4>
            <ul className="space-y-3 text-sm">
              {companyLinks.map((link) => (
                <li key={link.name}>
                  <button
                    onClick={() => navigate(link.path)}
                    className="hover:text-green-500 transition-colors duration-200 text-left focus:outline-none cursor-pointer"
                  >
                    {link.name}
                  </button>
                </li>
              ))}
            </ul>
          </div>

          {/* Featured Courses */}
          <div className="space-y-6">
            <h4 className="text-sm uppercase tracking-widest font-bold text-white">
              Courses
            </h4>
            <ul className="space-y-3 text-sm">
              {courseCategories.map((link) => (
                <li key={link.name}>
                  <button
                    onClick={() => navigate(link.path)}
                    className="hover:text-green-500 transition-colors duration-200 text-left focus:outline-none cursor-pointer"
                  >
                    {link.name}
                  </button>
                </li>
              ))}
            </ul>
          </div>

          {/* Contact Details */}
          <div className="space-y-6 col-span-2 md:col-span-1">
            <h4 className="text-sm uppercase tracking-widest font-bold text-white">
              Contact
            </h4>
            <ul className="space-y-4 text-sm">
              <li className="flex items-start gap-3">
                <Phone className="h-5 w-5 text-green-500 shrink-0 mt-0.5" />
                <span>0750 116 600</span>
              </li>
              <li className="flex items-start gap-3">
                <Mail className="h-5 w-5 text-green-500 shrink-0 mt-0.5" />
                <span>info@dazuaihub.com</span>
              </li>
              <li className="flex items-start gap-3">
                <MapPin className="h-5 w-5 text-green-500 shrink-0 mt-0.5" />
                <span>Witeithie House, Room 529, Thika Town</span>
              </li>
              <li className="flex items-start gap-3">
                <Clock className="h-5 w-5 text-green-500 shrink-0 mt-0.5" />
                <span>Mon - Sat: 8:00 AM - 5:00 PM</span>
              </li>
            </ul>
          </div>

        </div>

        {/* Bottom Bar */}
        <div className="border-t border-slate-900 pt-8 flex flex-col md:flex-row items-center justify-between gap-4 text-xs">
          <p>© {new Date().getFullYear()} Dazu hub Institute. All rights reserved.</p>
          <div className="flex gap-6">
            <button onClick={() => navigate('/privacy')} className="hover:text-white transition-colors duration-200 cursor-pointer focus:outline-none">Privacy Policy</button>
            <button onClick={() => navigate('/terms')} className="hover:text-white transition-colors duration-200 cursor-pointer focus:outline-none">Terms of Study</button>
            <button onClick={() => navigate('/contact')} className="hover:text-white transition-colors duration-200 cursor-pointer focus:outline-none">Support</button>
          </div>
        </div>
      </div>
    </footer>
  );
}
