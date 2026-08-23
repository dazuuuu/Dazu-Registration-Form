import React from 'react';
import { Play } from 'lucide-react';

export default function TestimonialSection() {
  return (
    <section className="py-16 bg-slate-950 text-white overflow-hidden relative">
      <div className="absolute top-0 right-0 -mr-48 -mt-48 w-96 h-96 bg-green-600/20 rounded-full blur-3xl pointer-events-none"></div>
      <div className="absolute bottom-0 left-0 -ml-48 -mb-48 w-96 h-96 bg-green-600/10 rounded-full blur-3xl pointer-events-none"></div>

      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
        <div className="text-center max-w-3xl mx-auto mb-12">
          <h2 className="text-3xl sm:text-4xl font-extrabold mb-4 tracking-tight">
            Hear From Our Students
          </h2>
          <div className="w-16 h-1 bg-green-600 mx-auto mb-6"></div>
          <p className="text-lg text-slate-400">
            Real stories from our graduates who have transformed their careers through our hands-on training programs.
          </p>
        </div>

        <div className="grid md:grid-cols-2 gap-8 items-center">
          
          {/* Video Testimonial 1 */}
          <div className="relative rounded-3xl overflow-hidden shadow-2xl ring-1 ring-white/10 group bg-slate-800 aspect-video flex items-center justify-center cursor-pointer">
            <div 
              className="absolute inset-0 bg-cover bg-center transition-transform duration-700 group-hover:scale-105"
              style={{ backgroundImage: 'url(https://images.unsplash.com/photo-1522202176988-66273c2fd55f?q=80&w=2071&auto=format&fit=crop)' }}
            />
            
            <button className="relative z-10 w-20 h-20 bg-white/20 backdrop-blur-md rounded-full flex items-center justify-center hover:bg-white/30 transition-all hover:scale-110 shadow-xl border border-white/30">
              <Play className="text-white fill-white ml-2" size={32} />
            </button>
            
            <div className="absolute bottom-0 left-0 right-0 p-8 bg-gradient-to-t from-slate-950/90 to-transparent">
              <p className="font-semibold text-xl text-white">"The practical approach changed my career path completely. I was able to deploy a real-world app within months."</p>
              <p className="text-green-400 mt-2 font-bold">Sarah W. — Software Engineering Graduate</p>
            </div>
          </div>

          {/* Video Testimonial 2 */}
          <div className="relative rounded-3xl overflow-hidden shadow-2xl ring-1 ring-white/10 group bg-slate-800 aspect-video flex items-center justify-center cursor-pointer">
            <div 
              className="absolute inset-0 bg-cover bg-center transition-transform duration-700 group-hover:scale-105"
              style={{ backgroundImage: 'url(https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?q=80&w=2076&auto=format&fit=crop)' }}
            />
            
            <button className="relative z-10 w-20 h-20 bg-white/20 backdrop-blur-md rounded-full flex items-center justify-center hover:bg-white/30 transition-all hover:scale-110 shadow-xl border border-white/30">
              <Play className="text-white fill-white ml-2" size={32} />
            </button>
            
            <div className="absolute bottom-0 left-0 right-0 p-8 bg-gradient-to-t from-slate-950/90 to-transparent">
              <p className="font-semibold text-xl text-white">"The mentors guided us through real-world penetration testing scenarios that prepared me for my current role as a Security Analyst."</p>
              <p className="text-green-400 mt-2 font-bold">David M. — Cybersecurity Alumni</p>
            </div>
          </div>

        </div>
      </div>
    </section>
  );
}
