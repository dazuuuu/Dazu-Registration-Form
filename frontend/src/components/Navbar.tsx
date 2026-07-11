import React, { useState } from 'react';
import { useRouter, Link } from '../router';
import { Menu, X, User, Phone } from 'lucide-react';

export default function Navbar() {
  const { path, navigate } = useRouter();
  const [mobileMenuOpen, setMobileMenuOpen] = useState(false);

  const menuItems = [
    { name: 'HOME', path: '/' },
    { name: 'COURSES', path: '/courses' },
    { name: 'ABOUT', path: '/about' },
    { name: 'PROJECTS', path: '/projects' },
    { name: 'BLOGS', path: '/blog' },
    { name: 'CONTACTS', path: '/contact' },
  ];

  const handleApplyClick = () => {
    setMobileMenuOpen(false);
    navigate('/apply');
  };

  const handleLoginClick = () => {
    setMobileMenuOpen(false);
    navigate('/login');
  };

  return (
    <nav className="sticky top-0 z-50 w-full bg-white border-b border-slate-100">
      {/* Top Contact Bar */}
      <div className="bg-[#0F172A] border-b border-slate-800 py-2">
        <div className="max-w-[1280px] mx-auto px-4 md:px-8 lg:px-12 flex items-center justify-end gap-2 text-white text-[11px] font-bold">
          <Phone className="h-3.5 w-3.5 text-[#15A24A]" />
          <span className="text-slate-300">Call / WhatsApp:</span>
          <a href="tel:+254750116600" className="text-[#15A24A] hover:text-[#128a3e] hover:underline transition-colors font-extrabold">
            +254 750 116600
          </a>
        </div>
      </div>

      <div className="max-w-[1280px] mx-auto px-4 md:px-8 lg:px-12">
        <div className="flex items-center justify-between h-20">
          
          {/* Logo - Matches image perfectly */}
          <button 
            onClick={() => navigate('/')}
            className="flex items-center gap-2 group cursor-pointer focus:outline-none"
            id="nav-logo"
          >
            <img 
              src="https://i.ibb.co/fzfZFtGc/223025.jpg" 
              alt="Dazu Hub Logo" 
              className="h-14 w-14 rounded-lg object-cover shrink-0 transition-transform duration-300 group-hover:scale-105" 
              referrerPolicy="no-referrer"
            />
            <div className="text-left font-sans leading-none">
              <div className="text-2xl font-extrabold text-slate-950 tracking-tight flex items-baseline">
                Dazu<span className="text-[#22C55E] ml-1">hub</span>
              </div>
              <div className="text-[10px] text-slate-500 font-extrabold tracking-tight mt-1">
                Institute
              </div>
            </div>
          </button>

          {/* Desktop Navigation */}
          <div className="hidden lg:flex items-center gap-8">
            {menuItems.map((item) => {
              const isActive = path === item.path || (item.path !== '/' && path.startsWith(item.path));
              return (
                <Link
                  key={item.name}
                  to={item.path}
                  id={`nav-link-${item.name.toLowerCase()}`}
                  className={`relative py-2 text-xs font-bold tracking-widest transition-colors duration-200 hover:text-[#22C55E] ${
                    isActive ? 'text-slate-900' : 'text-slate-700'
                  }`}
                >
                  {item.name}
                  {isActive && (
                    <span className="absolute bottom-0 left-0 right-0 h-[2px] bg-[#22C55E] rounded-full animate-fade-in" />
                  )}
                </Link>
              );
            })}
          </div>

          {/* Desktop CTA Buttons */}
          <div className="hidden lg:flex items-center gap-4">
            <a 
              href="tel:+254750116600" 
              className="text-xs font-extrabold text-slate-700 hover:text-[#15A24A] transition-all flex items-center gap-1.5 mr-2"
            >
              <Phone className="h-4 w-4 text-[#15A24A]" />
              +254 750 116600
            </a>
            <button
              onClick={handleApplyClick}
              id="nav-btn-apply"
              className="px-6 py-2.5 text-xs font-extrabold tracking-widest text-white bg-[#15A24A] hover:bg-[#128a3e] rounded-md transition-all duration-200 cursor-pointer uppercase"
            >
              APPLY
            </button>
            <button
              onClick={handleLoginClick}
              id="nav-btn-login"
              className="px-4 py-2 text-xs font-bold text-slate-700 bg-white border border-slate-200 hover:bg-slate-50 rounded-md transition-all duration-200 cursor-pointer flex items-center gap-1.5"
            >
              <User className="h-4 w-4 text-slate-500" />
              Login
            </button>
          </div>

          {/* Mobile Menu Button */}
          <div className="lg:hidden">
            <button
              onClick={() => setMobileMenuOpen(!mobileMenuOpen)}
              className="p-2 text-slate-600 hover:text-[#22C55E] rounded-xl focus:outline-none cursor-pointer"
              aria-label="Toggle menu"
              id="nav-mobile-toggle"
            >
              {mobileMenuOpen ? <X className="h-6 w-6" /> : <Menu className="h-6 w-6" />}
            </button>
          </div>
        </div>
      </div>

      {/* Mobile Animated Slide Menu */}
      {mobileMenuOpen && (
        <div className="lg:hidden border-t border-b border-slate-100 bg-white">
          <div className="px-4 pt-4 pb-6 space-y-2">
            {menuItems.map((item) => {
              const isActive = path === item.path;
              return (
                <button
                  key={item.name}
                  onClick={() => {
                    setMobileMenuOpen(false);
                    navigate(item.path);
                  }}
                  className={`block w-full text-left px-4 py-3 text-xs font-bold tracking-wider rounded-xl transition-colors ${
                    isActive 
                      ? 'text-[#22C55E] bg-green-50/50' 
                      : 'text-slate-700 hover:text-[#22C55E] hover:bg-slate-50'
                  }`}
                >
                  {item.name}
                </button>
              );
            })}
            <div className="pt-4 border-t border-slate-100 flex flex-col gap-3">
              <a
                href="tel:+254750116600"
                className="w-full py-3 text-center text-xs font-extrabold text-slate-700 hover:text-[#15A24A] bg-slate-50 rounded-md transition-all duration-200 flex items-center justify-center gap-1.5"
              >
                <Phone className="h-4 w-4 text-[#15A24A]" />
                +254 750 116600
              </a>
              <button
                onClick={handleApplyClick}
                className="w-full py-3 text-center text-xs font-extrabold tracking-widest text-white bg-[#15A24A] rounded-md hover:bg-[#128a3e] transition-all duration-200 uppercase"
              >
                APPLY
              </button>
              <button
                onClick={handleLoginClick}
                className="w-full py-3 text-center text-xs font-bold text-slate-700 bg-white border border-slate-200 rounded-md hover:bg-slate-50 transition-all duration-200 flex items-center justify-center gap-1.5"
              >
                <User className="h-4 w-4 text-slate-500" />
                Login
              </button>
            </div>
          </div>
        </div>
      )}
    </nav>
  );
}
