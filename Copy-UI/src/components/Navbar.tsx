import React from 'react';
import { Menu, X, BookOpen, User, Phone, Info, ChevronDown } from 'lucide-react';
import { useState } from 'react';

export default function Navbar() {
  const [isOpen, setIsOpen] = useState(false);
  const [isCoursesOpen, setIsCoursesOpen] = useState(false);

  return (
    <nav className="fixed w-full bg-white/90 backdrop-blur-md z-50 border-b border-gray-200">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="flex justify-between h-20">
          <div className="flex items-center">
            <div className="flex-shrink-0 flex items-center gap-2">
              <div className="w-10 h-10 bg-green-600 rounded-lg flex items-center justify-center text-white font-bold text-xl">
                D
              </div>
              <span className="font-bold text-xl md:text-2xl text-slate-900 tracking-tight">Dazu Training Institute</span>
            </div>
          </div>
          
          <div className="hidden xl:flex items-center space-x-6">
            <a href="/" className="text-slate-600 hover:text-green-600 text-sm font-semibold transition-colors uppercase">Home</a>
            
            <div className="relative group">
              <button className="flex items-center gap-1 text-slate-600 hover:text-green-600 text-sm font-semibold transition-colors uppercase">
                Courses <ChevronDown size={16} />
              </button>
              <div className="absolute top-full left-0 pt-4 hidden group-hover:block w-48">
                <div className="bg-white rounded-xl shadow-xl border border-gray-100 p-2 flex flex-col gap-1">
                  <a href="/courses/" className="px-4 py-2 text-sm font-semibold text-slate-700 hover:text-green-600 hover:bg-slate-50 rounded-lg transition-colors uppercase">All Courses</a>
                  <a href="/courses/free.php" className="px-4 py-2 text-sm font-semibold text-slate-700 hover:text-green-600 hover:bg-slate-50 rounded-lg transition-colors uppercase">Free Courses</a>
                </div>
              </div>
            </div>

            <a href="/about/" className="text-slate-600 hover:text-green-600 text-sm font-semibold transition-colors uppercase">About</a>
            <a href="/projects/" className="text-slate-600 hover:text-green-600 text-sm font-semibold transition-colors uppercase">Projects</a>
            <a href="/blogs/" className="text-slate-600 hover:text-green-600 text-sm font-semibold transition-colors uppercase">Blogs</a>
            <a href="/contact/" className="text-slate-600 hover:text-green-600 text-sm font-semibold transition-colors uppercase">Contacts</a>
          </div>
          
          <div className="hidden lg:flex items-center space-x-4">
            <a href="/apply/" className="bg-green-600 text-white px-5 py-2 rounded-full font-medium hover:bg-green-700 transition-colors focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2">
              APPLY
            </a>
            <a href="/auth/login.php" className="text-green-600 font-bold hover:text-green-700 transition-colors">
              Login
            </a>
          </div>

          <div className="flex items-center xl:hidden">
            <button
              onClick={() => setIsOpen(!isOpen)}
              className="text-slate-600 hover:text-slate-900 focus:outline-none p-2"
            >
              {isOpen ? <X size={24} /> : <Menu size={24} />}
            </button>
          </div>
        </div>
      </div>

      {/* Mobile menu */}
      {isOpen && (
        <div className="xl:hidden bg-white border-b border-gray-200">
          <div className="px-4 pt-2 pb-6 space-y-2">
            <a href="/" className="block px-3 py-3 rounded-md text-base font-medium text-slate-700 hover:text-green-600 hover:bg-slate-50 uppercase">
              Home
            </a>
            
            <div className="rounded-md overflow-hidden">
              <button 
                onClick={() => setIsCoursesOpen(!isCoursesOpen)}
                className="w-full flex items-center justify-between px-3 py-3 text-base font-medium text-slate-700 hover:text-green-600 hover:bg-slate-50 uppercase"
              >
                Courses
                <ChevronDown size={20} className={`transform transition-transform ${isCoursesOpen ? 'rotate-180' : ''}`} />
              </button>
              {isCoursesOpen && (
                <div className="bg-slate-50 px-4 py-2 space-y-2">
                  <a href="/courses/" className="block px-3 py-2 text-sm font-medium text-slate-600 hover:text-green-600 uppercase">All Courses</a>
                  <a href="/courses/free.php" className="block px-3 py-2 text-sm font-medium text-slate-600 hover:text-green-600 uppercase">Free Courses</a>
                </div>
              )}
            </div>

            <a href="/about/" className="block px-3 py-3 rounded-md text-base font-medium text-slate-700 hover:text-green-600 hover:bg-slate-50 uppercase">
              About
            </a>
            <a href="/projects/" className="block px-3 py-3 rounded-md text-base font-medium text-slate-700 hover:text-green-600 hover:bg-slate-50 uppercase">
              Projects
            </a>
            <a href="/blogs/" className="block px-3 py-3 rounded-md text-base font-medium text-slate-700 hover:text-green-600 hover:bg-slate-50 uppercase">
              Blogs
            </a>
            <a href="/contact/" className="block px-3 py-3 rounded-md text-base font-medium text-slate-700 hover:text-green-600 hover:bg-slate-50 uppercase">
              Contacts
            </a>
            <div className="pt-4 border-t border-slate-100 mt-4 space-y-3">
              <a href="/apply/" className="block w-full text-center bg-green-600 text-white px-4 py-3 rounded-lg font-medium hover:bg-green-700 transition-colors uppercase">
                Apply
              </a>
              <a href="/auth/login.php" className="block w-full text-center bg-slate-100 text-green-600 px-4 py-3 rounded-lg font-bold hover:bg-slate-200 transition-colors">
                Login
              </a>
            </div>
          </div>
        </div>
      )}
    </nav>
  );
}
