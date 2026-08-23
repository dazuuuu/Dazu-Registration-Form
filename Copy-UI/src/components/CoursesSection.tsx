import React, { useState } from 'react';
import { ArrowRight, Download, Star } from 'lucide-react';
import CourseDetailModal from './CourseDetailModal';

const categories = [
  "ALL",
  "SOFTWARE & WEB",
  "CYBERSECURITY",
  "SOFTWARE DEVELOPMENT",
  "WEB DEVELOPMENT",
  "AI COURSES",
  "BASIC SKILLS",
  "DATA"
];

const courses = [
  {
    id: 1,
    category: "AI COURSES",
    image: "https://images.unsplash.com/photo-1611162617474-5b21e879e113?q=80&w=1000&auto=format&fit=crop",
    rating: 4.8,
    reviews: 120,
    duration: "3 MONTHS",
    title: "AI and digital marketing",
    description: "Master content creation, ad targeting, and digital marketing powered by AI.",
  },
  {
    id: 2,
    category: "AI COURSES",
    image: "https://images.unsplash.com/photo-1485827404703-89b55fcc595e?q=80&w=1000&auto=format&fit=crop",
    rating: 4.8,
    reviews: 120,
    duration: "1 MONTH",
    title: "Ai automation",
    description: "Automate repetitive workflows and connect platforms using AI pipelines.",
  },
  {
    id: 3,
    category: "AI COURSES",
    image: "https://images.unsplash.com/photo-1620712943543-bcc4688e7485?q=80&w=1000&auto=format&fit=crop",
    rating: 4.8,
    reviews: 120,
    duration: "12 MONTHS",
    title: "AI ENGINEER",
    description: "Learn how to code, build, integrate and Train AI models.",
  },
  {
    id: 4,
    category: "CYBERSECURITY",
    image: "https://images.unsplash.com/photo-1550751827-4bd374c3f58b?q=80&w=1000&auto=format&fit=crop",
    rating: 4.8,
    reviews: 120,
    duration: "6 MONTHS",
    title: "Cyber security",
    description: "Cybersecurity is the practice of protecting networks, devices, and data from...",
  },
  {
    id: 5,
    category: "DATA",
    image: "https://images.unsplash.com/photo-1551288049-bebda4e38f71?q=80&w=1000&auto=format&fit=crop",
    rating: 4.9,
    reviews: 85,
    duration: "4 MONTHS",
    title: "Data Analytics",
    description: "Transform raw data into actionable insights using Python, SQL, and Power BI.",
  },
  {
    id: 6,
    category: "WEB DEVELOPMENT",
    image: "https://images.unsplash.com/photo-1547658719-da2b51169166?q=80&w=1000&auto=format&fit=crop",
    rating: 4.7,
    reviews: 210,
    duration: "6 MONTHS",
    title: "Full Stack Web Dev",
    description: "Learn React, Node.js, and databases to build complete web applications.",
  },
  {
    id: 7,
    category: "BASIC SKILLS",
    image: "https://images.unsplash.com/photo-1526379095098-d400fd0bfce8?q=80&w=1000&auto=format&fit=crop",
    rating: 4.8,
    reviews: 150,
    duration: "2 MONTHS",
    title: "Python Programming",
    description: "Master the fundamentals of Python for scripting, automation, and basics.",
  },
  {
    id: 8,
    category: "CYBERSECURITY",
    image: "https://images.unsplash.com/photo-1526304640581-d334cdbbf45e?q=80&w=1000&auto=format&fit=crop",
    rating: 4.9,
    reviews: 95,
    duration: "5 MONTHS",
    title: "Penetration Testing",
    description: "Learn advanced ethical hacking techniques to secure infrastructure.",
  },
  {
    id: 9,
    category: "WEB DEVELOPMENT",
    image: "https://images.unsplash.com/photo-1517134191118-9d595e4c8c2b?q=80&w=1000&auto=format&fit=crop",
    rating: 4.7,
    reviews: 110,
    duration: "3 MONTHS",
    title: "Frontend Engineering",
    description: "Focus on UI/UX, responsive design, React, and modern CSS frameworks.",
  },
  {
    id: 10,
    category: "AI COURSES",
    image: "https://images.unsplash.com/photo-1555255707-c07966088b7b?q=80&w=1000&auto=format&fit=crop",
    rating: 4.9,
    reviews: 180,
    duration: "8 MONTHS",
    title: "Machine Learning",
    description: "Dive deep into ML algorithms, neural networks, and deep learning.",
  },
  {
    id: 11,
    category: "SOFTWARE DEVELOPMENT",
    image: "https://images.unsplash.com/photo-1555066931-4365d14bab8c?q=80&w=1000&auto=format&fit=crop",
    rating: 4.8,
    reviews: 140,
    duration: "6 MONTHS",
    title: "Backend Development",
    description: "Design robust APIs, microservices, and manage database architectures.",
  },
  {
    id: 12,
    category: "SOFTWARE DEVELOPMENT",
    image: "https://images.unsplash.com/photo-1451187580459-43490279c0fa?q=80&w=1000&auto=format&fit=crop",
    rating: 4.8,
    reviews: 75,
    duration: "4 MONTHS",
    title: "Cloud Computing",
    description: "Deploy and scale applications using AWS, GCP, and modern DevOps tools.",
  },
  {
    id: 26,
    category: "SOFTWARE & WEB",
    image: "https://images.unsplash.com/photo-1574717024653-61fd2cf4d44d?q=80&w=1000&auto=format&fit=crop",
    rating: 4.9,
    reviews: 320,
    duration: "1 MONTHS",
    title: "Video editing & content creation",
    description: "Master video editing and content creation for TikTok, YouTube, and social media.",
  }
];

export default function CoursesSection() {
  const [activeCategory, setActiveCategory] = useState("ALL");
  const [selectedCourse, setSelectedCourse] = useState<any>(null);

  const displayedCourses = activeCategory === "ALL" 
    ? courses 
    : courses.filter(c => c.category === activeCategory);

  return (
    <section className="py-12 md:py-16 bg-slate-50 relative">
      {selectedCourse && (
        <CourseDetailModal 
          course={selectedCourse} 
          onClose={() => setSelectedCourse(null)} 
        />
      )}
      
      <div className="max-w-[1400px] mx-auto px-4 sm:px-6 lg:px-8">
        
        {/* Header Section */}
        <div className="text-center mb-8 md:mb-12">
          <h2 className="text-3xl md:text-5xl font-extrabold text-slate-900 mb-3 md:mb-4 tracking-tight">
            Our Courses
          </h2>
          <div className="w-16 h-1 bg-green-600 mx-auto mb-4 md:mb-6"></div>
          <p className="text-xs md:text-sm font-bold text-slate-400 uppercase tracking-widest">
            EXPLORE OUR TOP TECHNOLOGY CERTIFICATION TRACKS
          </p>
        </div>
        
        {/* Tabs - Mobile scrollable */}
        <div className="flex overflow-x-auto hide-scrollbar gap-2 md:gap-3 justify-start md:justify-center mb-6 md:mb-10 pb-2 md:pb-0 px-2 -mx-2 md:mx-0 md:px-0">
          {categories.map((cat) => (
            <button
              key={cat}
              onClick={() => setActiveCategory(cat)}
              className={`flex-shrink-0 px-4 md:px-5 py-2 md:py-2.5 rounded-full text-[11px] md:text-sm font-bold transition-all ${
                activeCategory === cat 
                  ? 'bg-green-600 text-white border-2 border-green-600 shadow-md' 
                  : 'bg-white text-slate-700 border-2 border-gray-100 hover:border-gray-200 shadow-sm'
              }`}
            >
              {cat}
            </button>
          ))}
        </div>

        {/* Tracks count */}
        <div className="flex justify-between items-center mb-4 md:mb-6">
          <p className="text-[10px] md:text-xs font-bold text-slate-500 uppercase tracking-wider">
            SHOWING <span className="text-green-600">{displayedCourses.length}</span> OF {courses.length} TRACKS
          </p>
        </div>
        
        {/* Course Grid */}
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 md:gap-6">
          {displayedCourses.map((course) => (
            <div key={course.id} className="bg-white rounded-2xl border border-gray-200 overflow-hidden shadow-sm hover:shadow-lg transition-shadow flex flex-col group cursor-pointer" onClick={() => setSelectedCourse(course)}>
              {/* Image Header */}
              <div className="relative h-44 md:h-48 w-full bg-slate-200 overflow-hidden">
                <img 
                  src={course.image} 
                  alt={course.title} 
                  className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                />
                <div className="absolute top-3 left-3 bg-green-600 text-white text-[9px] md:text-[10px] font-bold px-2 md:px-3 py-1 md:py-1.5 rounded-full uppercase tracking-wider">
                  {course.category}
                </div>
              </div>
              
              {/* Content Body */}
              <div className="p-4 md:p-6 flex-1 flex flex-col">
                <div className="flex items-center gap-2 mb-2 md:mb-3 text-[10px] md:text-xs font-bold text-slate-500">
                  <span className="flex items-center text-amber-500">
                    <Star size={12} className="fill-amber-500 mr-1 md:w-3.5 md:h-3.5" />
                    {course.rating}
                  </span>
                  <span>({course.reviews})</span>
                  <span className="w-1 h-1 rounded-full bg-slate-300"></span>
                  <span>{course.duration}</span>
                </div>
                
                <h3 className="text-lg md:text-xl font-extrabold text-slate-900 mb-2 md:mb-3 leading-tight group-hover:text-green-600 transition-colors">{course.title}</h3>
                <p className="text-xs md:text-sm text-slate-600 mb-4 md:mb-6 font-medium leading-relaxed flex-1 line-clamp-3">
                  {course.description}
                </p>
                
                <div className="border-t border-gray-100 pt-4 space-y-2.5">
                  <div className="flex gap-2">
                    <a
                      href="/apply/"
                      onClick={(e) => e.stopPropagation()}
                      className="flex-1 bg-green-600 hover:bg-green-700 text-white text-[11px] md:text-xs font-bold py-2 md:py-2.5 rounded-lg transition-colors flex items-center justify-center"
                    >
                      Apply Now
                    </a>
                    <a
                      href="/courses/"
                      onClick={(e) => e.stopPropagation()}
                      className="flex-1 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 text-[11px] md:text-xs font-bold py-2 md:py-2.5 rounded-lg transition-colors flex items-center justify-center"
                    >
                      View Course
                    </a>
                  </div>
                  <a href="/courses/" onClick={(e) => e.stopPropagation()} className="w-full bg-green-50 hover:bg-green-100 text-green-700 border border-green-200 text-[10px] md:text-[11px] font-bold py-2 md:py-2.5 rounded-lg flex items-center justify-center transition-colors uppercase tracking-wide">
                    <Download size={14} className="mr-1.5 md:mr-2" />
                    Brochure
                  </a>
                </div>
              </div>
            </div>
          ))}
        </div>
      </div>
    </section>
  );
}
