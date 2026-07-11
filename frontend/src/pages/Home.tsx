import React, { useState, useEffect } from 'react';
import { motion, AnimatePresence } from 'motion/react';
import { useRouter } from '../router';
import { coursesData } from '../data/courses';
import { useCourses } from '../hooks/useCourses';
import {
  ArrowRight, 
  Play, 
  X,
  Code, 
  Lock, 
  LineChart, 
  BrainCircuit, 
  Star,
  ChevronLeft,
  ChevronRight,
  Sparkles,
  Briefcase,
  GraduationCap,
  Users,
  Clock,
  CheckCircle2,
  BookOpen,
  Laptop,
  Cpu,
  Wifi,
  Tv,
  Sprout
} from 'lucide-react';

// Import high-quality generated assets
// @ts-ignore
import heroStudentImg from '../assets/images/hero_student_1783619555973.jpg';
// @ts-ignore
import softwareCourseImg from '../assets/images/software_dev_course_1783619578373.jpg';
// @ts-ignore
import cybersecurityCourseImg from '../assets/images/cybersecurity_course_1783619590151.jpg';
// @ts-ignore
import dataAnalyticsCourseImg from '../assets/images/data_analytics_course_1783619600593.jpg';
// @ts-ignore
import aiCourseImg from '../assets/images/ai_course_1783619614069.jpg';

// Animated Counter Helper
interface AnimatedCounterProps {
  target: number;
  duration?: number;
}

function AnimatedCounter({ target, duration = 2000 }: AnimatedCounterProps) {
  const [count, setCount] = useState(0);

  useEffect(() => {
    let startTimestamp: number | null = null;
    const step = (timestamp: number) => {
      if (!startTimestamp) startTimestamp = timestamp;
      const progress = Math.min((timestamp - startTimestamp) / duration, 1);
      const easedProgress = progress * (2 - progress); // easeOutQuad
      setCount(Math.floor(easedProgress * target));

      if (progress < 1) {
        requestAnimationFrame(step);
      } else {
        setCount(target);
      }
    };
    
    const animationFrame = requestAnimationFrame(step);
    return () => cancelAnimationFrame(animationFrame);
  }, [target, duration]);

  return <span>{count.toLocaleString()}</span>;
}

// Video Testimonial definitions
interface VideoTestimonial {
  id: string;
  name: string;
  track: string;
  title: string;
  duration: string;
  bgGradient: string;
  subtitles: string[];
  youtubeId: string;
}

const videoTestimonials: VideoTestimonial[] = [
  {
    id: "faith",
    name: "Faith Wambui",
    track: "Web Development",
    title: "Transitioning from hospitality to Full-Stack Developer",
    duration: "0:18",
    bgGradient: "from-emerald-500 to-teal-800",
    subtitles: [
      "I used to work long hours in hospitality with very little growth...",
      "But after joining Dazu Hub's software track, the hands-on labs changed everything.",
      "Within 3 months, I built standard web apps and landed my first developer role!",
      "The practical mentorship in Thika was exactly what I needed to launch my career."
    ],
    youtubeId: "EUY5XaeDsCI"
  },
  {
    id: "kevin",
    name: "Kevin Mwangi",
    track: "Cybersecurity & Networks",
    title: "Securing my first remote Security Operations Center role",
    duration: "0:18",
    bgGradient: "from-blue-600 to-indigo-900",
    subtitles: [
      "Getting into Cybersecurity was intimidating because of all the dry theory...",
      "At Dazu Hub, we practiced real ethical hacking and network security on live servers.",
      "That direct, hands-on physical experience was what set my resume apart.",
      "Now I work remotely for an international SOC team from right here in Thika!"
    ],
    youtubeId: "wsnMje68J9M"
  },
  {
    id: "naomi",
    name: "Naomi Chege",
    track: "Data Analytics",
    title: "From zero coding experience to corporate Data Analyst",
    duration: "0:18",
    bgGradient: "from-purple-600 to-fuchsia-950",
    subtitles: [
      "I had absolutely zero coding experience, not even basic HTML or scripting...",
      "The Data Analytics mentors took me step-by-step from Excel to complex SQL queries.",
      "We built interactive business dashboards for real Kenyan retail brands.",
      "I compiled my final project portfolio and got hired as a full-time Analyst!"
    ],
    youtubeId: "y0vgFgUXYAI"
  },
  {
    id: "david",
    name: "David Ochieng",
    track: "Ai & Digital Marketing",
    title: "Skyrocketing agency results with custom AI automated pipelines",
    duration: "0:18",
    bgGradient: "from-amber-500 to-orange-800",
    subtitles: [
      "We had a traditional marketing agency but struggled to scale client campaigns...",
      "The AI & Digital Marketing course taught us how to construct automated AI content loops.",
      "We learned to integrate LLM APIs and create smart campaigns that deliver massive ROI.",
      "We tripled our campaign conversion rates and halved our research time. Highly recommend!"
    ],
    youtubeId: "BK0wlEJ3nR4"
  }
];

function VideoPlayerModal({ video, onClose }: { video: VideoTestimonial; onClose: () => void }) {
  return (
    <div className="fixed inset-0 bg-slate-950/90 backdrop-blur-md flex items-center justify-center z-50 p-4 animate-fade-in">
      <div className="bg-slate-950 border border-slate-800 rounded-2xl w-full max-w-sm sm:max-w-md overflow-hidden relative flex flex-col aspect-[9/16] shadow-2xl animate-scale-up">
        
        {/* Modal Header */}
        <div className="flex items-center justify-between p-4 border-b border-slate-800 bg-slate-950 text-white shrink-0">
          <div className="text-left">
            <span className="text-[10px] uppercase tracking-widest text-[#15A24A] font-extrabold">{video.track}</span>
            <h3 className="font-extrabold text-xs sm:text-sm text-slate-100">{video.name} — Alumni Story</h3>
          </div>
          <button 
            onClick={onClose}
            className="p-1.5 hover:bg-slate-800 rounded-lg text-slate-400 hover:text-white cursor-pointer transition-colors"
          >
            <X className="h-5 w-5" />
          </button>
        </div>

        {/* Video Body */}
        <div className="relative flex-1 bg-black">
          <iframe 
            className="absolute inset-0 w-full h-full"
            src={`https://www.youtube.com/embed/${video.youtubeId}?autoplay=1&modestbranding=1&rel=0`}
            title={`${video.name} Video Testimonial`}
            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
            allowFullScreen
          ></iframe>
        </div>

      </div>
    </div>
  );
}

// Slide definitions for the hero slider
interface HeroSlide {
  id: number;
  badge: string;
  title1: string;
  titleAccent: string;
  description: string;
  image: string;
  imageAlt: string;
  cta1Text: string;
  cta1Path: string;
  cta2Text: string;
  cta2Path?: string;
  showPlayBtn: boolean;
  imagePosition?: string;
}

const heroSlides: HeroSlide[] = [
  {
    id: 1,
    badge: "Learn. Build. Succeed.",
    title1: "Upgrade Your Tech Skills.",
    titleAccent: "Build Your Future.",
    description: "Practical, hands-on training in high-demand tech skills to launch your career.",
    image: heroStudentImg,
    imageAlt: "Dazu Hub Tech Skills Student Profile",
    cta1Text: "Explore Courses",
    cta1Path: "/courses",
    cta2Text: "Watch Overview",
    showPlayBtn: true,
    imagePosition: "center top"
  },
  {
    id: 3,
    badge: "Future-Proof Your Career",
    title1: "Master AI & Analytics.",
    titleAccent: "Lead the Innovation.",
    description: "Empower your business or career with advanced Data Analytics and automated AI pipelines.",
    image: "https://i.pinimg.com/736x/4f/d6/5e/4fd65e9c73665937a0f7c5990de5ae35.jpg",
    imageAlt: "AI & Data Analytics Course",
    cta1Text: "Apply Online Now",
    cta1Path: "/apply",
    cta2Text: "Explore Courses",
    cta2Path: "/courses",
    showPlayBtn: false,
    imagePosition: "center"
  }
];

export default function Home() {
  const { navigate } = useRouter();
  const { courses } = useCourses();
  const displayCourses = courses.length ? courses : coursesData;
  const [currentSlide, setCurrentSlide] = useState(0);
  const [activeTestimonial, setActiveTestimonial] = useState(0);
  const [isVideoOpen, setIsVideoOpen] = useState(false);
  const [activeVideo, setActiveVideo] = useState<VideoTestimonial | null>(null);

  // Auto play slides
  useEffect(() => {
    const slideTimer = setInterval(() => {
      setCurrentSlide((prev) => (prev + 1) % heroSlides.length);
    }, 8000);
    return () => clearInterval(slideTimer);
  }, []);

  const testimonials = [
    {
      name: "Brian Mwangi",
      role: "Junior Full Stack Developer",
      company: "Saftech Solutions",
      image: "BM",
      quote: "Studying Software Development at Dazu Hub was the best decision of my career. The lab sessions are entirely hands-on. I had three production-ready web apps in my GitHub portfolio before graduating."
    },
    {
      name: "Mercy Chepkoech",
      role: "Data Analyst",
      company: "FinTech Africa",
      image: "MC",
      quote: "The Python and Tableau modules are world-class. Our trainers gave us real enterprise datasets to analyze. I was able to transition from an administrative role to technical Data Analytics smoothly!"
    },
    {
      name: "Emmanuel Kiprop",
      role: "Cybersecurity Analyst",
      company: "NetGuard Kenya",
      image: "EK",
      quote: "Ethical hacking training at Dazu Hub is highly professional. The structured labs simulated active cyber threats, which prepared me to clear my security certifications and land an amazing job."
    }
  ];

  // Auto play testimonials
  useEffect(() => {
    const timer = setInterval(() => {
      setActiveTestimonial((prev) => (prev + 1) % testimonials.length);
    }, 6000);
    return () => clearInterval(timer);
  }, [testimonials.length]);

  return (
    <div className="bg-white text-slate-900 overflow-x-hidden selection:bg-green-100 selection:text-green-800">
      
      {/* 1. HERO SECTION - Multi-slide Carousel with Slide Up Transitions */}
      <section className="relative min-h-[85vh] flex items-center pt-10 pb-24 bg-white overflow-hidden">
        
        {/* Subtle decorative grid of dots and floating shapes */}
        <div className="absolute top-10 right-10 w-24 h-24 opacity-25 bg-[radial-gradient(#CBD5E1_2px,transparent_2px)] [background-size:12px_12px] pointer-events-none hidden md:block"></div>
        <div className="absolute bottom-10 left-10 w-24 h-24 opacity-25 bg-[radial-gradient(#CBD5E1_2px,transparent_2px)] [background-size:12px_12px] pointer-events-none hidden md:block"></div>

        <div className="max-w-[1280px] mx-auto px-4 md:px-8 lg:px-12 w-full z-10 relative">
          <AnimatePresence mode="wait">
            <motion.div
              key={currentSlide}
              initial={{ opacity: 0, y: 50 }}
              animate={{ opacity: 1, y: 0 }}
              exit={{ opacity: 0, y: -50 }}
              transition={{ duration: 0.6, ease: [0.16, 1, 0.3, 1] }}
              className="grid grid-cols-1 lg:grid-cols-12 gap-12 items-center"
            >
              
              {/* Left Column: Text & CTAs */}
              <div className="lg:col-span-6 space-y-8 text-left">
                
                {/* Badge */}
                <div className="inline-block">
                  <span className="text-[#15A24A] font-extrabold text-sm tracking-wider uppercase">
                    {heroSlides[currentSlide].badge}
                  </span>
                  <span className="block h-[3px] w-12 bg-[#15A24A] mt-1 rounded-full"></span>
                </div>

                {/* Heading */}
                <h1 className="text-2xl xs:text-3xl sm:text-5xl md:text-6xl font-extrabold text-slate-950 tracking-tight leading-[1.15] font-sans">
                  <span className="block">{heroSlides[currentSlide].title1}</span>
                  <span className="block text-[#15A24A]">{heroSlides[currentSlide].titleAccent}</span>
                </h1>

                {/* Subheadline */}
                <p className="text-sm sm:text-lg text-slate-500 font-medium leading-relaxed max-w-lg">
                  {heroSlides[currentSlide].description}
                </p>
                
                {/* Call to Actions */}
                <div className="flex flex-row items-center gap-2 sm:gap-5 pt-2">
                  <button
                    onClick={() => navigate(heroSlides[currentSlide].cta1Path as any)}
                    className="px-3.5 py-3 sm:px-7 sm:py-4 text-[10px] sm:text-xs font-extrabold tracking-widest uppercase text-white bg-[#15A24A] hover:bg-[#128a3e] rounded-md transition-all duration-200 text-center cursor-pointer flex items-center justify-center gap-1.5 sm:gap-2 hover:-translate-y-0.5"
                  >
                    {heroSlides[currentSlide].cta1Text}
                    <ArrowRight className="h-3.5 w-3.5" />
                  </button>
                  
                  {heroSlides[currentSlide].showPlayBtn ? (
                    <button
                      onClick={() => setIsVideoOpen(true)}
                      className="px-3.5 py-3 sm:px-7 sm:py-4 text-[10px] sm:text-xs font-extrabold tracking-widest uppercase text-slate-900 border-2 border-[#15A24A] hover:bg-[#15A24A]/5 rounded-md transition-all duration-200 text-center cursor-pointer flex items-center justify-center gap-1.5 sm:gap-2 hover:-translate-y-0.5"
                    >
                      <Play className="h-3.5 w-3.5 fill-slate-900 ml-0.5" />
                      {heroSlides[currentSlide].cta2Text}
                    </button>
                  ) : (
                    <button
                      onClick={() => navigate((heroSlides[currentSlide].cta2Path || '/courses') as any)}
                      className="px-3.5 py-3 sm:px-7 sm:py-4 text-[10px] sm:text-xs font-extrabold tracking-widest uppercase text-slate-900 border-2 border-[#15A24A] hover:bg-[#15A24A]/5 rounded-md transition-all duration-200 text-center cursor-pointer flex items-center justify-center gap-1.5 sm:gap-2 hover:-translate-y-0.5"
                    >
                      {heroSlides[currentSlide].cta2Text}
                    </button>
                  )}
                </div>

                {/* Campus Information Overlay */}
                <div className="pt-8 border-t border-slate-100 flex items-center gap-6 text-xs font-semibold text-slate-400">
                  <span className="flex items-center gap-1.5">
                    <span className="w-2 h-2 rounded-full bg-[#15A24A]"></span>
                    Room 529 Physical Labs
                  </span>
                  <span className="flex items-center gap-1.5">
                    <span className="w-2 h-2 rounded-full bg-[#15A24A]"></span>
                    Thika Town Campus
                  </span>
                </div>
              </div>

              {/* Right Column: Custom Visual Studio Cutout & Background with Curved Orbit Arrows */}
              <div className="lg:col-span-6 relative flex justify-center items-center py-8">
                
                {/* Outer Decorative Floating Icons/Cards matching image exactly */}
                <div className="absolute inset-0 pointer-events-none z-20">
                  
                  {/* 1. Curved Arrow Top-Left to Right */}
                  <svg className="absolute top-[5%] left-[10%] w-[120px] h-[60px] text-slate-900 hidden md:block" viewBox="0 0 100 50" fill="none">
                    <path d="M10 40 C30 10, 70 10, 90 40" stroke="currentColor" strokeWidth="2.5" strokeDasharray="3 3" />
                    <polygon points="90,40 82,32 92,30" fill="currentColor" />
                  </svg>

                  {/* 2. Curved Arrow Bottom-Right to Left (Green) */}
                  <svg className="absolute bottom-[5%] left-[20%] w-[180px] h-[80px] text-[#15A24A] hidden md:block" viewBox="0 0 100 50" fill="none">
                    <path d="M90 10 C70 40, 30 40, 10 10" stroke="currentColor" strokeWidth="2.5" />
                    <polygon points="10,10 18,18 8,20" fill="currentColor" />
                  </svg>

                  {/* 3. Floating Braces Card {} */}
                  <div className="absolute top-[18%] left-[18%] flex items-center justify-center animate-[bounce_4.5s_infinite_100ms]">
                    <span className="text-slate-400 font-mono text-xs font-extrabold">{`{ }`}</span>
                  </div>

                  {/* 4. Floating Slash Brackets Card </> */}
                  <div className="absolute top-[10%] right-[10%] flex items-center justify-center animate-[bounce_5s_infinite_500ms]">
                    <span className="text-slate-400 font-mono text-xs font-extrabold">{`</>`}</span>
                  </div>

                  {/* 5. Floating JavaScript tag 'JS' */}
                  <div className="absolute bottom-[40%] left-[2%] text-[#F7DF1E] font-extrabold text-xs transform rotate-[-12deg] animate-[bounce_4s_infinite_200ms]">
                    JS
                  </div>

                  {/* 6. Floating CSS tag 'CSS' */}
                  <div className="absolute bottom-[28%] right-[2%] text-[#1572B6] font-extrabold text-xs transform rotate-[8deg] animate-[bounce_4.8s_infinite_300ms]">
                    CSS
                  </div>

                  {/* 7. Floating tech symbols */}
                  <span className="absolute bottom-[25%] right-[20%] font-mono text-slate-300 font-bold text-lg pointer-events-none">{`</>`}</span>
                </div>

                {/* Main Background Squircle Shape (The rounded green card) */}
                <div className="relative w-[280px] sm:w-[360px] h-[280px] sm:h-[360px] bg-[#15A24A] rounded-[48px] rotate-[-6deg] overflow-visible flex items-center justify-center">
                  
                  {/* Visual Accent Layer */}
                  <div className="absolute inset-2 border-2 border-dashed border-white/30 rounded-[42px]"></div>

                  {/* Perfect Portrait Overlay */}
                  <div className="absolute inset-0 rounded-[48px] overflow-hidden rotate-[6deg] transform hover:rotate-0 transition-transform duration-500 cursor-pointer">
                    <img 
                      src={heroSlides[currentSlide].image} 
                      alt={heroSlides[currentSlide].imageAlt} 
                      className="w-full h-full object-cover"
                      style={{ objectPosition: heroSlides[currentSlide].imagePosition || "center" }}
                      referrerPolicy="no-referrer"
                    />
                  </div>

                </div>

              </div>

            </motion.div>
          </AnimatePresence>

          {/* Slider Controls and Navigation Dots */}
          <div className="absolute bottom-[-10px] left-1/2 -translate-x-1/2 z-20 flex items-center gap-4">
            <button
              onClick={() => setCurrentSlide((prev) => (prev - 1 + heroSlides.length) % heroSlides.length)}
              className="p-2 rounded-full border border-slate-200 bg-white/90 backdrop-blur-sm hover:bg-white text-slate-700 hover:text-[#15A24A] transition-all shadow-sm cursor-pointer"
              aria-label="Previous slide"
            >
              <ChevronLeft className="h-4 w-4" />
            </button>
            
            <div className="flex gap-2">
              {heroSlides.map((_, index) => (
                <button
                  key={index}
                  onClick={() => setCurrentSlide(index)}
                  className={`h-2.5 rounded-full transition-all duration-300 cursor-pointer ${
                    index === currentSlide ? 'w-6 bg-[#15A24A]' : 'w-2.5 bg-slate-200 hover:bg-slate-300'
                  }`}
                  aria-label={`Go to slide ${index + 1}`}
                />
              ))}
            </div>

            <button
              onClick={() => setCurrentSlide((prev) => (prev + 1) % heroSlides.length)}
              className="p-2 rounded-full border border-slate-200 bg-white/90 backdrop-blur-sm hover:bg-white text-slate-700 hover:text-[#15A24A] transition-all shadow-sm cursor-pointer"
              aria-label="Next slide"
            >
              <ChevronRight className="h-4 w-4" />
            </button>
          </div>

        </div>
      </section>

      {/* 2. STATS BAR SECTION */}
      <section className="bg-slate-50 border-y border-slate-100 py-12">
        <div className="max-w-[1280px] mx-auto px-4 md:px-8 lg:px-12">
          <div className="grid grid-cols-2 md:grid-cols-4 gap-8 text-center">
            <div className="space-y-1">
              <span className="block text-4xl font-extrabold text-slate-950">
                <AnimatedCounter target={1200} />+
              </span>
              <span className="text-[10px] uppercase tracking-widest font-bold text-slate-400">
                Students Trained
              </span>
            </div>
            <div className="space-y-1">
              <span className="block text-4xl font-extrabold text-slate-950">
                <AnimatedCounter target={14} />+
              </span>
              <span className="text-[10px] uppercase tracking-widest font-bold text-slate-400">
                Practical Courses
              </span>
            </div>
            <div className="space-y-1">
              <span className="block text-4xl font-extrabold text-slate-950">
                <AnimatedCounter target={350} />+
              </span>
              <span className="text-[10px] uppercase tracking-widest font-bold text-slate-400">
                Projects Completed
              </span>
            </div>
            <div className="space-y-1">
              <span className="block text-4xl font-extrabold text-slate-950">
                <AnimatedCounter target={25} />+
              </span>
              <span className="text-[10px] uppercase tracking-widest font-bold text-slate-400">
                Industry Mentors
              </span>
            </div>
          </div>
        </div>
      </section>

      {/* 3. POPULAR COURSES SECTION - Styled exactly like the 4-card grid in the design */}
      <section className="py-24 bg-white">
        <div className="max-w-[1280px] mx-auto px-4 md:px-8 lg:px-12 text-center">
          
          {/* Centered Heading */}
          <div className="max-w-xl mx-auto space-y-3 mb-16">
            <h2 className="text-3xl font-extrabold tracking-tight text-slate-950 relative inline-block pb-3 font-sans">
              Our Courses
              <span className="absolute bottom-0 left-1/4 right-1/4 h-[3px] bg-[#15A24A] rounded-full"></span>
            </h2>
            <p className="text-slate-400 text-xs font-semibold uppercase tracking-widest pt-2">
              Explore Our Top Technology Certification Tracks
            </p>
          </div>          {/* Modern 3D Rectangle Course Cards Grid with Image, Ratings, Price and Apply Button */}
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6 text-left">
            {displayCourses.map((course, index) => {
              const IconComponent = course.icon;
              return (
                <motion.div 
                  key={course.id}
                  onClick={() => navigate(`/apply?course=${encodeURIComponent(course.title)}`)}
                  className="group relative bg-white border border-slate-200 rounded-2xl overflow-hidden flex flex-col justify-between h-full cursor-pointer hover:border-[#15A24A] transition-all duration-300 select-none shadow-sm"
                  id={`course-card-${course.id}`}
                  initial={{ opacity: 0, y: 30 }}
                  whileInView={{ opacity: 1, y: 0 }}
                  viewport={{ once: true, margin: "-40px" }}
                  whileHover={{ 
                    y: -6, 
                    boxShadow: "0 20px 25px -5px rgba(21, 162, 74, 0.1), 0 10px 10px -5px rgba(21, 162, 74, 0.04)" 
                  }}
                  whileTap={{ scale: 0.98 }}
                  transition={{ 
                    type: "spring",
                    stiffness: 300,
                    damping: 24,
                    delay: index * 0.05
                  }}
                >
                  {/* Image Container with overlay */}
                  <div className="relative h-44 w-full overflow-hidden bg-slate-100">
                    <img 
                      src={course.image} 
                      alt={course.title}
                      referrerPolicy="no-referrer"
                      className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                    />
                    {/* Category badge overlay */}
                    <span className="absolute top-3 left-3 text-[10px] font-bold text-white bg-[#15A24A] px-2.5 py-1 rounded-full uppercase tracking-wider shadow-sm">
                      {course.category}
                    </span>
                    {/* Compact Icon overlay */}
                    <div className="absolute bottom-3 right-3 p-2 bg-white/90 backdrop-blur-xs text-[#15A24A] border border-green-100 rounded-lg shadow-sm">
                      <IconComponent className="h-4 w-4" />
                    </div>
                  </div>

                  {/* Course Details */}
                  <div className="p-5 flex-1 flex flex-col justify-between space-y-4">
                    <div className="space-y-2">
                      {/* Rating Line */}
                      <div className="flex items-center gap-1.5 text-xs">
                        <div className="flex items-center text-amber-500">
                          <Star className="h-3.5 w-3.5 fill-current" />
                        </div>
                        <span className="font-bold text-slate-800">{course.rating.toFixed(1)}</span>
                        <span className="text-slate-400 font-semibold">({course.ratingCount})</span>
                        <span className="text-slate-300">•</span>
                        <span className="text-slate-500 font-bold uppercase tracking-wider text-[10px]">
                          {course.duration}
                        </span>
                      </div>

                      <h3 className="font-extrabold text-slate-900 text-base tracking-tight group-hover:text-[#15A24A] transition-colors duration-150 line-clamp-1">
                        {course.title}
                      </h3>

                      <p className="text-slate-500 text-xs font-semibold leading-relaxed line-clamp-2">
                        {course.description}
                      </p>
                    </div>

                    <div className="pt-4 border-t border-slate-100 flex items-center justify-between">
                      <div>
                        <span className="block text-[10px] font-bold text-slate-400 uppercase tracking-widest">Tuition Fee</span>
                        <span className="text-[#15A24A] font-extrabold text-sm sm:text-base">
                          {course.price}
                        </span>
                      </div>
                      
                      <button 
                        onClick={(e) => {
                          e.stopPropagation();
                          navigate(`/apply?course=${encodeURIComponent(course.title)}`);
                        }}
                        className="px-4 py-2 bg-[#15A24A] hover:bg-[#128a3e] text-white text-xs font-bold rounded-lg transition-colors shadow-sm cursor-pointer flex items-center gap-1"
                        id={`course-apply-btn-${course.id}`}
                      >
                        Apply Now
                        <ArrowRight className="h-3 w-3" />
                      </button>
                    </div>
                  </div>
                </motion.div>
              );
            })}
          </div>

          {/* 4 Core Features Row below the cards - Matches the design perfectly */}
          <div className="mt-20 pt-12 border-t border-slate-100 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8 text-left">
            
            <div className="flex items-start gap-4">
              <div className="p-2.5 bg-green-50 text-[#15A24A] rounded-xl shrink-0 border border-green-100">
                <Briefcase className="h-5 w-5" />
              </div>
              <div>
                <h4 className="font-extrabold text-slate-950 text-sm">Hands-on Projects</h4>
                <p className="text-slate-500 text-xs mt-1 font-medium leading-normal">Build real-world projects.</p>
              </div>
            </div>

            <div className="flex items-start gap-4">
              <div className="p-2.5 bg-green-50 text-[#15A24A] rounded-xl shrink-0 border border-green-100">
                <GraduationCap className="h-5 w-5" />
              </div>
              <div>
                <h4 className="font-extrabold text-slate-950 text-sm">Industry Recognized</h4>
                <p className="text-slate-500 text-xs mt-1 font-medium leading-normal">Get certified and boost your career.</p>
              </div>
            </div>

            <div className="flex items-start gap-4">
              <div className="p-2.5 bg-green-50 text-[#15A24A] rounded-xl shrink-0 border border-green-100">
                <Users className="h-5 w-5" />
              </div>
              <div>
                <h4 className="font-extrabold text-slate-950 text-sm">Expert Mentors</h4>
                <p className="text-slate-500 text-xs mt-1 font-medium leading-normal">Learn from industry professionals.</p>
              </div>
            </div>

            <div className="flex items-start gap-4">
              <div className="p-2.5 bg-green-50 text-[#15A24A] rounded-xl shrink-0 border border-green-100">
                <Clock className="h-5 w-5" />
              </div>
              <div>
                <h4 className="font-extrabold text-slate-950 text-sm">Flexible Learning</h4>
                <p className="text-slate-500 text-xs mt-1 font-medium leading-normal">Study at your own pace.</p>
              </div>
            </div>

          </div>

        </div>
      </section>

      {/* 4. PHYSICAL LAB OVERVIEW & LIFE AT CAMPUS */}
      <section className="py-24 bg-slate-50 border-t border-slate-100">
        <div className="max-w-[1280px] mx-auto px-4 md:px-8 lg:px-12">
          
          <div className="grid grid-cols-1 lg:grid-cols-12 gap-12 items-center">
            
            {/* Left side: Interactive representation of Thika Campus Room 529 */}
            <div className="lg:col-span-6 space-y-6">
              <span className="text-[#15A24A] text-xs font-extrabold uppercase tracking-widest px-3 py-1 bg-green-50 border border-green-100 rounded-full">
                Physical Campus Environment
              </span>
              <h2 className="text-3xl md:text-4xl font-extrabold tracking-tight text-slate-950">
                Our Physical Lab in Thika Town
              </h2>
              <p className="text-slate-500 text-sm font-medium leading-relaxed">
                Step into <strong>Room 529</strong>, our modern, fully-equipped development space located in the heart of Thika Town. Meet other passionate coders, work under professional mentorship, and access our elite local server infrastructure.
              </p>

              <div className="space-y-3 pt-2">
                <div className="flex items-start gap-2 text-slate-700 text-sm font-semibold">
                  <CheckCircle2 className="h-5 w-5 text-[#15A24A] shrink-0 mt-0.5" />
                  <span>High-speed fiber optic connection optimized for AWS, Vercel & Firebase deployments.</span>
                </div>
                <div className="flex items-start gap-2 text-slate-700 text-sm font-semibold">
                  <CheckCircle2 className="h-5 w-5 text-[#15A24A] shrink-0 mt-0.5" />
                  <span>Ergonomic software workstations paired with external development test screens.</span>
                </div>
                <div className="flex items-start gap-2 text-slate-700 text-sm font-semibold">
                  <CheckCircle2 className="h-5 w-5 text-[#15A24A] shrink-0 mt-0.5" />
                  <span>Interactive presentation screens for weekly group code reviews & presentation sessions.</span>
                </div>
              </div>

              <div className="pt-4 flex gap-4">
                <button
                  onClick={() => navigate('/about')}
                  className="px-6 py-3 text-xs font-bold bg-white hover:bg-slate-100 border border-slate-200 text-slate-800 rounded-md transition-colors cursor-pointer"
                >
                  Explore Campus Tour
                </button>
                <button
                  onClick={() => navigate('/contact')}
                  className="px-6 py-3 text-xs font-bold text-white bg-[#15A24A] hover:bg-[#128a3e] rounded-md transition-colors cursor-pointer"
                >
                  Visit Room 529
                </button>
              </div>
            </div>

            {/* Right side: visual mock representing student lab layout */}
            <div className="lg:col-span-6 bg-white border border-slate-200 rounded-3xl p-8 space-y-6">
              <div className="flex justify-between items-center pb-4 border-b border-slate-100">
                <h3 className="text-xs uppercase tracking-widest font-extrabold text-slate-400">Campus Facilities</h3>
                <span className="text-[10px] uppercase tracking-widest font-bold text-[#15A24A] bg-green-50 px-2.5 py-0.5 rounded-full border border-green-100">Room 529 Active</span>
              </div>

              <div className="space-y-4">
                <div className="grid grid-cols-2 gap-4">
                  <div className="border border-slate-100 rounded-xl p-4 text-left space-y-2">
                    <div className="p-2 bg-green-50 text-[#15A24A] rounded-lg w-fit">
                      <Laptop className="h-4.5 w-4.5" />
                    </div>
                    <h4 className="font-extrabold text-xs text-slate-950">Modern Workstations</h4>
                    <p className="text-[11px] text-slate-500 font-medium leading-normal">Equipped with external dual-monitors and comfortable developer seating.</p>
                  </div>
                  <div className="border border-slate-100 rounded-xl p-4 text-left space-y-2">
                    <div className="p-2 bg-green-50 text-[#15A24A] rounded-lg w-fit">
                      <Cpu className="h-4.5 w-4.5" />
                    </div>
                    <h4 className="font-extrabold text-xs text-slate-950">Local Lab Servers</h4>
                    <p className="text-[11px] text-slate-500 font-medium leading-normal">Deploy and test your full-stack software safely inside our local subnets.</p>
                  </div>
                  <div className="border border-slate-100 rounded-xl p-4 text-left space-y-2">
                    <div className="p-2 bg-green-50 text-[#15A24A] rounded-lg w-fit">
                      <Wifi className="h-4.5 w-4.5" />
                    </div>
                    <h4 className="font-extrabold text-xs text-slate-950">High-Speed Optic</h4>
                    <p className="text-[11px] text-slate-500 font-medium leading-normal">Fiber internet access ensuring immediate cloud deployments and research.</p>
                  </div>
                  <div className="border border-slate-100 rounded-xl p-4 text-left space-y-2">
                    <div className="p-2 bg-green-50 text-[#15A24A] rounded-lg w-fit">
                      <Tv className="h-4.5 w-4.5" />
                    </div>
                    <h4 className="font-extrabold text-xs text-slate-950">Interactive Screens</h4>
                    <p className="text-[11px] text-slate-500 font-medium leading-normal">Used for live code walkthroughs, design sprints, and slide reviews.</p>
                  </div>
                </div>

                <div className="grid grid-cols-2 gap-4">
                  <div className="border border-slate-100 rounded-xl p-4 text-center">
                    <span className="block text-2xl font-extrabold text-slate-950">98.4%</span>
                    <span className="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mt-1">Placement rate</span>
                  </div>
                  <div className="border border-slate-100 rounded-xl p-4 text-center">
                    <span className="block text-2xl font-extrabold text-slate-950">250+</span>
                    <span className="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mt-1">Live Portfolios</span>
                  </div>
                </div>
              </div>
            </div>

          </div>

        </div>
      </section>

      {/* 5. TESTIMONIALS SECTION (ALUMNI OUTCOMES) */}
      <section className="py-24 bg-white">
        <div className="max-w-[1280px] mx-auto px-4 md:px-8 lg:px-12">
          
          {/* Video Testimonials Section */}
          <div className="text-center mb-16">
            <span className="text-[#15A24A] text-xs uppercase tracking-widest font-extrabold px-3 py-1 bg-green-50 border border-green-100 rounded-full inline-block">
              Alumni Experience
            </span>
            <h2 className="text-3xl md:text-4xl font-extrabold text-slate-950 tracking-tight flex items-center justify-center gap-2.5 mt-4">
              <span className="inline-block p-1.5 bg-green-50 text-[#15A24A] rounded-lg border border-green-100">
                <Play className="h-5 w-5 fill-[#15A24A]" />
              </span>
              Video Testimonials
            </h2>
            <p className="text-slate-500 text-sm font-medium leading-relaxed max-w-lg mx-auto mt-3">
              Watch Dazu Hub Alumni Share Their Learning Experience
            </p>
          </div>

          {/* 4 Video Testimonial placeholders - 2 per row for uniformity */}
          <div className="grid grid-cols-2 gap-3 sm:gap-8 max-w-5xl mx-auto px-1 sm:px-0">
            {videoTestimonials.map((video) => (
              <div 
                key={video.id}
                onClick={() => setActiveVideo(video)}
                className="group relative rounded-xl sm:rounded-2xl overflow-hidden border-2 border-slate-950 cursor-pointer transition-all duration-200 hover:-translate-y-1 active:translate-y-0 select-none"
                style={{ aspectRatio: '16/16.38' }}
              >
                {/* Thumbnail Cover styling */}
                <div className="absolute inset-0 bg-gradient-to-br from-slate-900 to-slate-950 opacity-90 group-hover:opacity-100 transition-opacity flex flex-col items-center justify-center p-2 sm:p-6 text-center">
                  
                  {/* Background track gradient fallback */}
                  <div className={`absolute inset-0 bg-gradient-to-br ${video.bgGradient} opacity-70 group-hover:opacity-85 transition-opacity`}></div>
                  
                  {/* Dark gradient mask for readability */}
                  <div className="absolute inset-0 bg-slate-950/20 group-hover:bg-slate-950/5 transition-colors"></div>
 
                  {/* Green Centered Play button */}
                  <div className="w-8 h-8 sm:w-12 sm:h-12 bg-[#15A24A] text-white rounded-full flex items-center justify-center relative z-10 group-hover:scale-110 transition-transform border border-white/20 shrink-0">
                    <Play className="h-2.5 w-2.5 sm:h-4 sm:w-4 fill-white ml-0.5" />
                  </div>
 
                  {/* Overlay info at bottom */}
                  <div className="absolute bottom-1.5 left-1.5 right-1.5 sm:bottom-4 sm:left-4 sm:right-4 text-left z-10 bg-black/50 backdrop-blur-xs p-1.5 sm:p-3 rounded-lg sm:rounded-xl border border-white/5">
                    <span className="text-[7px] sm:text-[9px] font-extrabold uppercase tracking-widest text-green-300 block mb-0.5 truncate">
                      {video.track}
                    </span>
                    <h4 className="text-[9px] sm:text-sm font-extrabold text-white leading-tight truncate">
                      {video.name}
                    </h4>
                    <div className="flex items-center justify-between text-[8px] sm:text-[10px] text-slate-300 font-semibold mt-0.5 sm:mt-1">
                      <p className="truncate mr-2 hidden xs:block">{video.title}</p>
                      <span className="flex items-center gap-0.5 sm:gap-1 shrink-0 bg-white/10 px-1 sm:px-1.5 py-0.5 rounded-xs">
                        <Clock className="h-2 w-2 sm:h-3 sm:w-3" />
                        {video.duration}
                      </span>
                    </div>
                  </div>
 
                </div>
              </div>
            ))}
          </div>

          {/* Student Success Stories Section */}
          <div className="max-w-2xl mx-auto space-y-4 text-center mt-24 mb-16">
            <span className="text-[#15A24A] text-xs uppercase tracking-widest font-extrabold">
              Proven Outcomes
            </span>
            <h2 className="text-3xl md:text-4xl font-extrabold tracking-tight text-slate-950">
              Student Success Stories
            </h2>
            <p className="text-slate-500 text-sm font-medium leading-relaxed max-w-lg mx-auto">
              Read how Dazu Hub alumni launched their high-growth technology careers right here in Thika.
            </p>
          </div>

          <div className="max-w-3xl mx-auto">
            {/* Active Testimonial Card */}
            <div className="bg-slate-50 border border-slate-200 rounded-3xl p-8 md:p-12 text-left relative overflow-hidden">
              <span className="absolute top-8 right-8 text-8xl font-serif text-slate-200/50 select-none">“</span>
              <div className="space-y-6 relative z-10">
                <p className="text-base sm:text-lg text-slate-700 italic font-medium leading-relaxed">
                  "{testimonials[activeTestimonial].quote}"
                </p>
                <div className="flex items-center gap-4 pt-4">
                  <div className="h-12 w-12 bg-[#15A24A] rounded-full flex items-center justify-center text-white font-extrabold text-sm tracking-wide">
                    {testimonials[activeTestimonial].image}
                  </div>
                  <div>
                    <h4 className="text-base font-bold text-slate-950">{testimonials[activeTestimonial].name}</h4>
                    <p className="text-xs text-slate-500 font-semibold uppercase tracking-wider mt-0.5">
                      {testimonials[activeTestimonial].role} at <span className="text-[#15A24A] font-bold">{testimonials[activeTestimonial].company}</span>
                    </p>
                  </div>
                </div>
              </div>
            </div>

            {/* Carousel Pagination Controls */}
            <div className="flex items-center justify-between mt-8">
              <div className="flex items-center gap-2">
                {testimonials.map((_, idx) => (
                  <button
                    key={idx}
                    onClick={() => setActiveTestimonial(idx)}
                    className={`h-2.5 rounded-full transition-all duration-300 ${
                      activeTestimonial === idx ? 'w-8 bg-[#15A24A]' : 'w-2.5 bg-slate-200'
                    }`}
                    aria-label={`Go to testimonial ${idx + 1}`}
                  ></button>
                ))}
              </div>
              <div className="flex gap-2">
                <button
                  onClick={() => setActiveTestimonial((prev) => (prev - 1 + testimonials.length) % testimonials.length)}
                  className="p-2.5 border border-slate-200 bg-white hover:bg-slate-50 rounded-lg text-slate-700 cursor-pointer transition-colors"
                  aria-label="Previous testimonial"
                >
                  <ChevronLeft className="h-4 w-4" />
                </button>
                <button
                  onClick={() => setActiveTestimonial((prev) => (prev + 1) % testimonials.length)}
                  className="p-2.5 border border-slate-200 bg-white hover:bg-slate-50 rounded-lg text-slate-700 cursor-pointer transition-colors"
                  aria-label="Next testimonial"
                >
                  <ChevronRight className="h-4 w-4" />
                </button>
              </div>
            </div>
          </div>

        </div>
      </section>

      {/* 6. CALL TO ACTION (CTA) SECTION */}
      <section className="py-20 bg-white">
        <div className="max-w-[1280px] mx-auto px-4 md:px-8 lg:px-12">
          <div className="bg-[#0F172A] text-white rounded-3xl p-12 md:p-16 text-center max-w-4xl mx-auto relative overflow-hidden">
            
            <div className="absolute top-0 left-0 w-32 h-32 border-b border-r border-slate-900 opacity-20 pointer-events-none"></div>
            <div className="absolute bottom-0 right-0 w-48 h-48 border-t border-l border-slate-900 opacity-20 pointer-events-none"></div>

            <div className="space-y-8 relative z-10">
              <h2 className="text-3xl sm:text-4xl font-extrabold tracking-tight text-white leading-tight font-sans">
                Start Your Technology Career Today
              </h2>
              <p className="text-slate-400 text-sm sm:text-base max-w-xl mx-auto font-medium leading-relaxed">
                Unlock practical high-income skills, build world-class portfolios, and graduate with industry-recognized certificates. Visit our physical lab in Thika Town.
              </p>
              
              <div className="flex flex-col sm:flex-row items-center justify-center gap-4 pt-4">
                <button
                  onClick={() => navigate('/apply')}
                  className="w-full sm:w-auto px-8 py-3.5 text-xs font-bold tracking-widest text-white bg-[#15A24A] hover:bg-[#128a3e] rounded-md transition-all duration-200 text-center cursor-pointer shadow-md"
                >
                  APPLY NOW
                </button>
                <button
                  onClick={() => navigate('/contact')}
                  className="w-full sm:w-auto px-8 py-3.5 text-xs font-bold tracking-widest text-white bg-transparent hover:bg-slate-900 border border-slate-800 rounded-md transition-all duration-200 text-center cursor-pointer"
                >
                  CONTACT US
                </button>
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* 7. OVERVIEW VIDEO MODAL DIALOG */}
      {isVideoOpen && (
        <div className="fixed inset-0 bg-slate-950/90 backdrop-blur-md flex items-center justify-center z-50 p-4 animate-fade-in">
          <div className="bg-slate-950 border border-slate-800 rounded-2xl w-full max-w-sm sm:max-w-md overflow-hidden relative flex flex-col aspect-[9/16] shadow-2xl animate-scale-up">
            
            {/* Modal Header */}
            <div className="flex items-center justify-between p-4 border-b border-slate-800 bg-slate-950 text-white shrink-0">
              <div className="text-left">
                <span className="text-[10px] uppercase tracking-widest text-[#15A24A] font-extrabold">OVERVIEW</span>
                <h3 className="font-extrabold text-xs sm:text-sm text-slate-100 font-sans tracking-tight">Dazu Hub Tech Institute</h3>
              </div>
              <button 
                onClick={() => setIsVideoOpen(false)}
                className="p-1.5 hover:bg-slate-800 rounded-lg text-slate-400 hover:text-white cursor-pointer transition-colors"
              >
                <X className="h-5 w-5" />
              </button>
            </div>
            
            {/* Embedded overview video / presentation */}
            <div className="relative flex-1 bg-black">
              <iframe 
                className="absolute inset-0 w-full h-full"
                src="https://www.youtube.com/embed/C_DaXqajluk?autoplay=1&modestbranding=1&rel=0"
                title="Dazu Hub Overview Video"
                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                allowFullScreen
              ></iframe>
            </div>

          </div>
        </div>
      )}

      {activeVideo && (
        <VideoPlayerModal 
          video={activeVideo} 
          onClose={() => setActiveVideo(null)} 
        />
      )}

    </div>
  );
}
