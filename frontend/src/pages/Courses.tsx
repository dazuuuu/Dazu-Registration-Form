import React, { useState, useEffect } from 'react';
import { useRouter } from '../router';
import { coursesData, type Course } from '../data/courses';
import { useCourses } from '../hooks/useCourses';
import { 
  Search, 
  Filter, 
  BookOpen, 
  Clock, 
  Tag, 
  X, 
  ArrowRight, 
  CheckCircle,
  Sparkles,
  Info,
  Star
} from 'lucide-react';

export default function Courses() {
  const { navigate } = useRouter();
  const { courses } = useCourses();
  const catalog = courses.length ? courses : coursesData;
  const [searchQuery, setSearchQuery] = useState('');
  const [selectedCategory, setSelectedCategory] = useState<string>('All');
  const [selectedLevel, setSelectedLevel] = useState<string>('All');
  const [selectedCourse, setSelectedCourse] = useState<Course | null>(null);

  // Parse state from URL to auto-open course or apply filters if any
  useEffect(() => {
    const params = new URLSearchParams(window.location.search);
    const cat = params.get('category');
    if (cat) {
      setSelectedCategory(cat);
    }
  }, []);

  const categories = ['All', 'Software & Web', 'AI & Data Science', 'Security & Infrastructure', 'Creative & Marketing'];
  const levels = ['All', 'Beginner', 'Intermediate', 'Advanced'];

  const filteredCourses = catalog.filter((course) => {
    const matchesSearch = 
      course.title.toLowerCase().includes(searchQuery.toLowerCase()) || 
      course.description.toLowerCase().includes(searchQuery.toLowerCase());
    
    const matchesCategory = selectedCategory === 'All' || course.category === selectedCategory;
    const matchesLevel = selectedLevel === 'All' || course.level === selectedLevel;

    return matchesSearch && matchesCategory && matchesLevel;
  });

  const handleApplyNow = (courseTitle: string) => {
    setSelectedCourse(null);
    // Navigate to apply page with course in state or query param
    const encodedCourse = encodeURIComponent(courseTitle);
    navigate(`/apply?course=${encodedCourse}`);
  };

  return (
    <div className="bg-white min-h-screen py-16">
      <div className="max-w-[1280px] mx-auto px-4 md:px-8 lg:px-12">
        
        {/* Page Header */}
        <div className="max-w-2xl text-left space-y-4 mb-16">
          <span className="text-green-600 text-xs uppercase tracking-widest font-extrabold px-3 py-1 bg-green-50 border border-green-200 rounded-full w-fit">
            Practical Certifications
          </span>
          <h1 className="text-4xl md:text-5xl font-extrabold tracking-tight text-slate-900">
            Our Technology Courses
          </h1>
          <p className="text-slate-600 font-medium leading-relaxed">
            Choose from 10 comprehensive, lab-driven certification tracks structured around industrial development pipelines. Study physically at Thika Town.
          </p>
        </div>

        {/* Filter and Search Bar Controller Grid */}
        <div className="grid grid-cols-1 lg:grid-cols-12 gap-6 mb-12">
          
          {/* Search Input Box */}
          <div className="relative lg:col-span-4">
            <span className="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none text-slate-400">
              <Search className="h-5 w-5" />
            </span>
            <input
              type="text"
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              placeholder="Search courses, skills, tools..."
              className="w-full pl-11 pr-4 py-3.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-900 placeholder-slate-400 font-medium focus:outline-none focus:border-green-600 focus:bg-white text-sm"
              id="courses-search-input"
            />
          </div>

          {/* Category Filter Pills (Desktop View Scrollable horizontal rail) */}
          <div className="lg:col-span-8 flex flex-wrap items-center gap-2">
            {categories.map((category) => (
              <button
                key={category}
                onClick={() => setSelectedCategory(category)}
                className={`px-4 py-3 text-xs font-bold uppercase tracking-wider rounded-xl transition-all border cursor-pointer ${
                  selectedCategory === category
                    ? 'bg-green-600 text-white border-green-600 font-extrabold'
                    : 'bg-slate-50 text-slate-600 border-slate-200 hover:bg-slate-100 hover:text-slate-900'
                }`}
              >
                {category}
              </button>
            ))}
          </div>

        </div>

        {/* Level and Counts Line info */}
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-100 pb-6 mb-10 text-sm">
          <div className="flex items-center gap-2">
            <Filter className="h-4 w-4 text-slate-400" />
            <span className="font-bold text-slate-500 uppercase tracking-widest text-xs">Level:</span>
            <div className="flex gap-1">
              {levels.map((lvl) => (
                <button
                  key={lvl}
                  onClick={() => setSelectedLevel(lvl)}
                  className={`px-3 py-1 rounded-lg text-xs font-bold transition-all border cursor-pointer ${
                    selectedLevel === lvl
                      ? 'bg-slate-900 text-white border-slate-900'
                      : 'bg-white text-slate-500 border-slate-200 hover:bg-slate-50'
                  }`}
                >
                  {lvl}
                </button>
              ))}
            </div>
          </div>
          <span className="font-bold text-slate-500 uppercase tracking-wider text-xs">
            Showing <span className="text-green-600">{filteredCourses.length}</span> of {coursesData.length} available tracks
          </span>
        </div>

        {/* Empty search results layout */}
        {filteredCourses.length === 0 && (
          <div className="text-center py-24 border border-dashed border-slate-200 rounded-2xl bg-slate-50">
            <Info className="h-12 w-12 text-slate-400 mx-auto mb-4" />
            <h3 className="text-lg font-bold text-slate-900">No courses found</h3>
            <p className="text-slate-500 text-sm mt-1 max-w-sm mx-auto">
              We couldn't find any courses matching your current filter selections. Try searching with other tech words or reset filters.
            </p>
            <button
              onClick={() => {
                setSearchQuery('');
                setSelectedCategory('All');
                setSelectedLevel('All');
              }}
              className="mt-6 px-5 py-2.5 bg-green-600 hover:bg-green-700 text-white text-xs font-bold rounded-xl cursor-pointer"
            >
              Reset Filters
            </button>
          </div>
        )}

        {/* Responsive Grid list of active cards with Image, Rating, Price and Actions */}
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
          {filteredCourses.map((course) => {
            const IconComponent = course.icon;
            return (
              <div
                key={course.id}
                onClick={() => setSelectedCourse(course)}
                className="group bg-white border border-slate-200 rounded-2xl overflow-hidden flex flex-col justify-between h-full cursor-pointer hover:border-green-600 transition-all duration-300 hover:-translate-y-1.5 shadow-sm hover:shadow-md"
                id={`course-card-${course.id}`}
              >
                {/* Image Container with overlays */}
                <div className="relative h-48 w-full bg-slate-100 overflow-hidden">
                  <img 
                    src={course.image} 
                    alt={course.title} 
                    referrerPolicy="no-referrer"
                    className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                  />
                  {/* Category overlay */}
                  <span className="absolute top-3 left-3 text-[10px] font-extrabold text-white bg-green-600 px-2.5 py-1 rounded-full uppercase tracking-wider shadow-sm">
                    {course.category}
                  </span>
                  {/* Icon Container overlay */}
                  <div className="absolute bottom-3 right-3 p-2 bg-white/95 text-green-600 border border-slate-100 rounded-xl shadow-sm">
                    <IconComponent className="h-5 w-5" />
                  </div>
                </div>

                {/* Content Container */}
                <div className="p-6 flex-1 flex flex-col justify-between space-y-4">
                  <div className="space-y-3">
                    {/* Rating & Stats row */}
                    <div className="flex items-center gap-1.5 text-xs">
                      <div className="flex items-center text-amber-500">
                        <Star className="h-3.5 w-3.5 fill-current" />
                      </div>
                      <span className="font-extrabold text-slate-800">{course.rating.toFixed(1)}</span>
                      <span className="text-slate-400 font-semibold">({course.ratingCount} reviews)</span>
                      <span className="text-slate-300">•</span>
                      <span className="text-slate-500 font-bold uppercase tracking-wider text-[10px] flex items-center gap-1">
                        <Clock className="h-3.5 w-3.5 text-slate-400" />
                        {course.duration}
                      </span>
                    </div>

                    <h3 className="text-xl font-extrabold text-slate-900 group-hover:text-green-600 transition-colors duration-200">
                      {course.title}
                    </h3>
                    
                    <p className="text-slate-500 text-xs font-semibold leading-relaxed line-clamp-2">
                      {course.description}
                    </p>
                  </div>

                  <div className="pt-4 border-t border-slate-100 flex items-center justify-between">
                    <div>
                      <span className="block text-[9px] font-bold text-slate-400 uppercase tracking-wider">Tuition Fee</span>
                      <span className="text-green-600 font-extrabold text-base">
                        {course.price}
                      </span>
                    </div>
                    
                    <div className="flex items-center gap-2">
                      <button 
                        onClick={(e) => {
                          e.stopPropagation();
                          handleApplyNow(course.title);
                        }}
                        className="px-3.5 py-2 bg-green-600 hover:bg-green-700 text-white text-xs font-bold rounded-lg transition-colors shadow-sm cursor-pointer flex items-center gap-1"
                      >
                        Apply
                      </button>
                      <button 
                        onClick={(e) => {
                          e.stopPropagation();
                          setSelectedCourse(course);
                        }}
                        className="px-3.5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-lg transition-colors cursor-pointer flex items-center gap-1 border border-slate-200"
                      >
                        Syllabus
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            );
          })}
        </div>

      </div>

      {/* DETAILED SYLLABUS DETAIL MODAL DRAWER */}
      {selectedCourse && (
        <div className="fixed inset-0 bg-slate-900/80 backdrop-blur-xs flex items-center justify-center z-50 p-4">
          <div className="bg-white border-2 border-slate-900 rounded-2xl w-full max-w-2xl overflow-hidden">
            
            {/* Header info bar */}
            <div className="flex items-center justify-between p-6 border-b border-slate-200 bg-slate-50">
              <div className="flex items-center gap-3">
                <div className="p-2.5 bg-green-600 rounded-xl text-white">
                  <BookOpen className="h-5 w-5" />
                </div>
                <div>
                  <h3 className="text-lg font-bold text-slate-900">{selectedCourse.title}</h3>
                  <p className="text-xs font-bold text-slate-400 uppercase tracking-wider">{selectedCourse.category}</p>
                </div>
              </div>
              <button 
                onClick={() => setSelectedCourse(null)}
                className="p-1.5 hover:bg-slate-200 rounded-lg text-slate-500 cursor-pointer"
              >
                <X className="h-5 w-5" />
              </button>
            </div>

            {/* Scrollable details container */}
            <div className="p-6 md:p-8 space-y-8 max-h-[70vh] overflow-y-auto text-left">
              
              {/* Brief intro */}
              <div className="space-y-2">
                <span className="text-xs uppercase font-extrabold tracking-widest text-slate-400">Course Overview</span>
                <p className="text-slate-700 text-sm leading-relaxed font-semibold">
                  {selectedCourse.description}
                </p>
              </div>

              {/* Specs Grid info list */}
              <div className="grid grid-cols-3 gap-4 p-4 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold">
                <div>
                  <span className="block text-slate-400 uppercase tracking-wider text-[10px] mb-1">Duration</span>
                  <span className="text-slate-950 flex items-center gap-1">
                    <Clock className="h-4 w-4 text-green-600" />
                    {selectedCourse.duration}
                  </span>
                </div>
                <div>
                  <span className="block text-slate-400 uppercase tracking-wider text-[10px] mb-1">Tuition Fee</span>
                  <span className="text-slate-950 flex items-center gap-1">
                    <Tag className="h-4 w-4 text-green-600" />
                    {selectedCourse.price}
                  </span>
                </div>
                <div>
                  <span className="block text-slate-400 uppercase tracking-wider text-[10px] mb-1">Expert Level</span>
                  <span className="text-slate-950 flex items-center gap-1">
                    <Sparkles className="h-4 w-4 text-green-600" />
                    {selectedCourse.level}
                  </span>
                </div>
              </div>

              {/* Syllabus checklist */}
              <div className="space-y-4">
                <span className="text-xs uppercase font-extrabold tracking-widest text-slate-400">Syllabus Breakdown</span>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                  {selectedCourse.syllabus.map((item, idx) => (
                    <div key={idx} className="flex items-start gap-2.5 p-3 bg-white border border-slate-100 rounded-lg">
                      <CheckCircle className="h-4.5 w-4.5 text-green-600 shrink-0 mt-0.5" />
                      <span className="text-xs font-bold text-slate-700 leading-normal">{item}</span>
                    </div>
                  ))}
                </div>
              </div>

              {/* Educational venue location note */}
              <div className="bg-green-50 border border-green-100 rounded-xl p-4 text-xs font-semibold text-green-800 leading-relaxed flex items-start gap-2.5">
                <Info className="h-4.5 w-4.5 text-green-700 shrink-0 mt-0.5" />
                <div>
                  <strong>Lab Location:</strong> This is a highly practical lab-oriented training. Lectures are taught in physical coding labs at <strong>Witeithie House, Room 529, Thika Town</strong>. Learning materials are available online as well.
                </div>
              </div>

            </div>

            {/* Bottom action panel */}
            <div className="p-6 border-t border-slate-200 bg-slate-50 flex items-center justify-between gap-4">
              <span className="text-xs font-bold text-slate-500">Includes Certificate & Job Prep</span>
              <div className="flex gap-3">
                <button
                  onClick={() => setSelectedCourse(null)}
                  className="px-5 py-2.5 text-xs font-bold text-slate-700 bg-white border border-slate-200 rounded-xl hover:bg-slate-100 cursor-pointer"
                >
                  Close Details
                </button>
                <button
                  onClick={() => handleApplyNow(selectedCourse.title)}
                  className="px-5 py-2.5 text-xs font-bold text-white bg-green-600 border border-transparent rounded-xl hover:bg-green-700 flex items-center gap-1.5 cursor-pointer"
                >
                  Enroll Now
                  <ArrowRight className="h-4 w-4" />
                </button>
              </div>
            </div>

          </div>
        </div>
      )}

    </div>
  );
}
