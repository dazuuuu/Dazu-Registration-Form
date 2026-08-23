import React from 'react';
import { ArrowRight, Calendar, User } from 'lucide-react';

const blogs = [
  {
    id: 1,
    title: "How to Transition into Tech in 2024",
    excerpt: "Discover the most effective strategies to shift your career into the tech industry, regardless of your background.",
    category: "Career Guide",
    date: "Aug 15, 2026",
    author: "Admin",
    image: "https://images.unsplash.com/photo-1516321318423-f06f85e504b3?q=80&w=800&auto=format&fit=crop"
  },
  {
    id: 2,
    title: "Why Python is Still the King of Data",
    excerpt: "An in-depth look at why Python continues to dominate the data analytics and machine learning landscape.",
    category: "Data Science",
    date: "Aug 12, 2026",
    author: "Admin",
    image: "https://images.unsplash.com/photo-1526379095098-d400fd0bfce8?q=80&w=800&auto=format&fit=crop"
  },
  {
    id: 3,
    title: "Top 5 Cybersecurity Threats This Year",
    excerpt: "Stay ahead of malicious actors by understanding the most prominent vulnerabilities and attack vectors facing businesses today.",
    category: "Security",
    date: "Aug 08, 2026",
    author: "Admin",
    image: "https://images.unsplash.com/photo-1550751827-4bd374c3f58b?q=80&w=800&auto=format&fit=crop"
  }
];

export default function BlogsSection() {
  return (
    <section className="py-16 bg-slate-50">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="flex flex-col sm:flex-row justify-between items-end mb-12 gap-6">
          <div>
            <h2 className="text-3xl sm:text-4xl font-extrabold text-slate-900 mb-4 tracking-tight">
              Latest Insights
            </h2>
            <div className="w-16 h-1 bg-green-600 mb-4"></div>
            <p className="text-lg text-slate-600 max-w-2xl">
              Explore knowledge from our growing library of <span className="font-bold text-green-600">93 blogs</span> covering tech trends, coding tutorials, and career advice.
            </p>
          </div>
          <a href="/blogs/" className="flex items-center gap-2 text-green-600 font-bold hover:text-green-700 transition-colors whitespace-nowrap group">
            View all 93 blogs <ArrowRight size={20} className="transform transition-transform group-hover:translate-x-1" />
          </a>
        </div>

        <div className="grid md:grid-cols-3 gap-8">
          {blogs.map((blog) => (
            <div key={blog.id} className="bg-white rounded-2xl overflow-hidden shadow-sm hover:shadow-xl transition-shadow border border-slate-100 flex flex-col">
              <div className="relative h-48 overflow-hidden group">
                <img 
                  src={blog.image} 
                  alt={blog.title} 
                  className="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105"
                />
                <div className="absolute top-4 left-4 bg-white/90 backdrop-blur-sm px-3 py-1 rounded-full text-xs font-bold text-green-700 uppercase tracking-wide">
                  {blog.category}
                </div>
              </div>
              <div className="p-6 flex flex-col flex-1">
                <div className="flex items-center gap-4 text-xs font-semibold text-slate-400 mb-4 uppercase tracking-wider">
                  <span className="flex items-center gap-1"><Calendar size={14} /> {blog.date}</span>
                  <span className="flex items-center gap-1"><User size={14} /> {blog.author}</span>
                </div>
                <a href="/blogs/" className="block text-xl font-extrabold text-slate-900 mb-3 leading-snug hover:text-green-600 cursor-pointer transition-colors">
                  {blog.title}
                </a>
                <p className="text-slate-600 mb-6 flex-1">
                  {blog.excerpt}
                </p>
                <a href="/blogs/" className="text-slate-900 font-bold hover:text-green-600 flex items-center gap-2 group/btn self-start transition-colors">
                  Read article <ArrowRight size={16} className="transform transition-transform group-hover/btn:translate-x-1" />
                </a>
              </div>
            </div>
          ))}
        </div>
      </div>
    </section>
  );
}
