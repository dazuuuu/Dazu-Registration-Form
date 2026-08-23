import React from 'react';

export default function StatsSection() {
  return (
    <section className="py-12 bg-white border-b border-gray-100">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="grid grid-cols-2 md:grid-cols-4 gap-8 text-center">
          <div>
            <div className="text-5xl md:text-6xl font-black text-slate-900 mb-4">1,200+</div>
            <div className="text-xs sm:text-sm font-bold text-slate-400 uppercase tracking-widest">Students Trained</div>
          </div>
          <div>
            <div className="text-5xl md:text-6xl font-black text-slate-900 mb-4">14+</div>
            <div className="text-xs sm:text-sm font-bold text-slate-400 uppercase tracking-widest">Practical Courses</div>
          </div>
          <div>
            <div className="text-5xl md:text-6xl font-black text-slate-900 mb-4">350+</div>
            <div className="text-xs sm:text-sm font-bold text-slate-400 uppercase tracking-widest">Projects Completed</div>
          </div>
          <div>
            <div className="text-5xl md:text-6xl font-black text-slate-900 mb-4">25+</div>
            <div className="text-xs sm:text-sm font-bold text-slate-400 uppercase tracking-widest">Industry Mentors</div>
          </div>
        </div>
      </div>
    </section>
  );
}
