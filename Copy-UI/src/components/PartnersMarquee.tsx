import React from 'react';

const partners = [
  { name: 'CompTIA', color: 'text-red-600' },
  { name: 'Cisco', color: 'text-blue-600' },
  { name: 'NITA', color: 'text-green-700' },
  { name: 'TVETA', color: 'text-blue-800' },
  { name: "Eddy's AI Lab", color: 'text-purple-600' },
  { name: 'Infinity Habits', color: 'text-indigo-600' }
];

// Double the list for seamless scrolling
const duplicatedPartners = [...partners, ...partners];

export default function PartnersMarquee() {
  return (
    <div className="py-8 bg-white overflow-hidden border-b border-gray-100">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mb-8 text-center">
         <p className="text-sm font-semibold text-slate-500 uppercase tracking-wider">Our Partners</p>
      </div>
      <div className="relative w-full flex overflow-hidden">
        <div className="flex w-max animate-scroll items-center gap-8 px-4">
           {duplicatedPartners.map((partner, i) => (
              <div key={i} className="flex-shrink-0 flex items-center justify-center h-24 w-48 bg-slate-50 border border-slate-100 rounded-2xl shadow-sm hover:shadow-md transition-shadow">
                 <span className={`font-black text-2xl tracking-tight ${partner.color}`}>{partner.name}</span>
              </div>
           ))}
        </div>
      </div>
    </div>
  );
}
