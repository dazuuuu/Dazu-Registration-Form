import React from 'react';
import { MapPin, Phone, Mail, Globe, Users, Camera, Play, Clock } from 'lucide-react';

export default function Footer() {
  return (
    <footer className="bg-slate-950 text-slate-300 py-16 border-t border-slate-900">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-12">
          {/* Brand Column */}
          <div>
            <div className="flex items-center gap-2 mb-6">
              <div className="w-10 h-10 bg-green-600 rounded flex items-center justify-center text-white font-bold text-xl">
                D
              </div>
              <div className="flex flex-col">
                <span className="font-bold text-xl text-white tracking-tight leading-none mb-1">Dazuhub</span>
                <span className="font-bold text-xl text-white tracking-tight leading-none">Institute</span>
              </div>
            </div>
            <div className="flex gap-4">
              <a href="https://www.tiktok.com/@dazuhub" target="_blank" rel="noopener noreferrer" className="w-10 h-10 rounded-full bg-slate-900 flex items-center justify-center hover:bg-green-600 hover:text-white transition-colors" title="TikTok">
                <Globe size={18} />
              </a>
              <a href="https://www.facebook.com/people/Dazu-Training-institute/61573237914007/" target="_blank" rel="noopener noreferrer" className="w-10 h-10 rounded-full bg-slate-900 flex items-center justify-center hover:bg-green-600 hover:text-white transition-colors" title="Facebook">
                <Users size={18} />
              </a>
              <a href="https://www.instagram.com/dazuhub/" target="_blank" rel="noopener noreferrer" className="w-10 h-10 rounded-full bg-slate-900 flex items-center justify-center hover:bg-green-600 hover:text-white transition-colors" title="Instagram">
                <Camera size={18} />
              </a>
              <a href="https://music.youtube.com/@dazuhub" target="_blank" rel="noopener noreferrer" className="w-10 h-10 rounded-full bg-slate-900 flex items-center justify-center hover:bg-green-600 hover:text-white transition-colors" title="YouTube">
                <Play size={18} className="ml-0.5" />
              </a>
            </div>
          </div>
          
          {/* Company Column */}
          <div>
            <h4 className="text-white font-bold text-lg mb-6">Company</h4>
            <ul className="space-y-3">
              <li><a href="/about/" className="hover:text-green-400 transition-colors">About Us</a></li>
              <li><a href="/projects/" className="hover:text-green-400 transition-colors">Student Projects</a></li>
              <li><a href="/blogs/" className="hover:text-green-400 transition-colors">Institute Blog</a></li>
              <li><a href="/contact/" className="hover:text-green-400 transition-colors">Contact &amp; Support</a></li>
            </ul>
          </div>

          {/* Courses Column */}
          <div>
            <h4 className="text-white font-bold text-lg mb-6">Courses</h4>
            <ul className="space-y-3">
              <li><a href="/courses/" className="hover:text-green-400 transition-colors">Software Development</a></li>
              <li><a href="/courses/" className="hover:text-green-400 transition-colors">Artificial Intelligence</a></li>
              <li><a href="/courses/" className="hover:text-green-400 transition-colors">Cybersecurity &amp; Networks</a></li>
              <li><a href="/courses/" className="hover:text-green-400 transition-colors">Creative Graphics &amp; Design</a></li>
            </ul>
          </div>

          {/* Contact Column */}
          <div>
            <h4 className="text-white font-bold text-lg mb-6">Contact</h4>
            <ul className="space-y-4">
              <li className="flex gap-3 items-center">
                <Phone size={20} className="text-green-500 flex-shrink-0" />
                <span>+254 722 922 454</span>
              </li>
              <li className="flex gap-3 items-center">
                <Mail size={20} className="text-green-500 flex-shrink-0" />
                <span>admin@dazuhub.com</span>
              </li>
              <li className="flex gap-3 items-start">
                <MapPin size={20} className="text-green-500 mt-1 flex-shrink-0" />
                <span className="leading-relaxed">Witeithie House 5H FLOOR, Room 529, Thika Town and Nairobi branch Cianda house 4TH floor room 406B</span>
              </li>
              <li className="flex gap-3 items-start">
                <Clock size={20} className="text-green-500 mt-1 flex-shrink-0" />
                <span>Mon - Sat: 8:00 AM - 5:00 PM</span>
              </li>
            </ul>
          </div>
        </div>
        
        {/* Bottom Bar */}
        <div className="border-t border-slate-900 mt-16 pt-8 flex flex-col md:flex-row justify-between items-center gap-4 text-sm text-slate-500">
          <p>&copy; 2026 Dazu hub Institute. All rights reserved.</p>
          <div className="flex gap-6">
            <a href="#" className="hover:text-green-400 transition-colors">Support</a>
            <a href="#" className="hover:text-green-400 transition-colors">Terms of Study</a>
          </div>
        </div>
      </div>
    </footer>
  );
}
