import React, { useState, useEffect, useRef } from 'react';
import WhyChooseUs from './WhyChooseUs';
import CTA from './CTA';
import Footer from './Footer';
import { api, type Course } from '../api/client';

// --- SUB-COMPONENTS --- //
interface FeaturedCourse {
    id: number;
    name: string;
    description: string;
    image: string;
    price?: number;
    duration?: string;
}

const stock: Record<string, string> = {
  WD: 'https://images.unsplash.com/photo-1498050108023-c5249f4df085?w=700&q=80',
  CY: 'https://images.unsplash.com/photo-1550751827-4bd374c3f58b?w=700&q=80',
  DA: 'https://images.unsplash.com/photo-1551288049-bebda4e38f71?w=700&q=80',
  AI: 'https://images.unsplash.com/photo-1677442136019-21780ecad995?w=700&q=80',
};

interface Slide {
    title: string;
    subtitle: string;
    description: string;
    image: string;
    illustration: React.ReactNode;
    overlayText?: string;
}

const FeaturedCourseCard: React.FC<{ course: FeaturedCourse, onSelect: (courseName: string, courseId?: number) => void }> = ({ course, onSelect }) => (
    <div className="bg-white dark:bg-slate-800 rounded-3xl shadow-sm hover:shadow-xl transition-shadow duration-300 border border-slate-200/80 dark:border-slate-700/80 flex flex-col overflow-hidden card-hover-effect glowing-border-effect">
        <div className="w-full h-40 overflow-hidden">
            <img src={course.image} alt={`Promotional image for ${course.name}`} className="w-full h-full object-cover" />
        </div>
        <div className="p-5 flex flex-col flex-grow">
            <h3 className="text-lg font-bold text-slate-900 dark:text-slate-100 mb-2">{course.name}</h3>
            <p className="text-slate-600 dark:text-slate-400 text-sm flex-grow">{course.description}</p>
        </div>
        <div className="p-5 pt-0 mt-auto">
            <button
                onClick={() => onSelect(course.name, course.id)}
                className="w-full text-white font-semibold px-4 py-2.5 rounded-xl hover:opacity-90 transition-opacity"
                style={{ background: 'var(--gradient)' }}
            >
                Register
            </button>
        </div>
    </div>
);

const ServiceCard: React.FC<{ image: string; title: string; description: string }> = ({ image, title, description }) => (
    <div className="bg-white dark:bg-slate-800 p-6 rounded-3xl shadow-sm border border-slate-200/80 dark:border-slate-700/80 text-center card-hover-effect glowing-border-effect flex flex-col">
        <div className="mb-4">
            <img src={image} alt={`Illustrative image for ${title}`} className="w-full h-32 object-cover rounded-2xl shadow-md" />
        </div>
        <h3 className="text-xl font-bold text-slate-900 dark:text-slate-100 mb-2">{title}</h3>
        <p className="text-slate-600 dark:text-slate-400 flex-grow">{description}</p>
    </div>
);

// --- COUNTING STATS COMPONENTS --- //

const useCountUp = (end: number, duration = 2000, startAnimation: boolean) => {
    const [count, setCount] = useState(0);
    const stepTime = 1000 / 60; // 60 fps

    useEffect(() => {
        if (!startAnimation) return;

        let frame = 0;
        const totalFrames = Math.round(duration / stepTime);
        const counter = setInterval(() => {
            frame++;
            const progress = frame / totalFrames;
            const currentCount = Math.round(end * progress);
            
            setCount(currentCount > end ? end : currentCount);

            if (frame === totalFrames) {
                clearInterval(counter);
            }
        }, stepTime);

        return () => clearInterval(counter);
    }, [end, duration, startAnimation]);

    return count;
};


const StatItem: React.FC<{ value: number; label: string; suffix: string; inView: boolean }> = ({ value, label, suffix, inView }) => {
    const count = useCountUp(value, 2000, inView);
    return (
        <div className="text-center">
            <p className="text-3xl sm:text-4xl md:text-5xl font-extrabold gradient-text" aria-hidden="true">{count}{suffix}</p>
            <span className="sr-only">{value}{suffix}</span>
            <p className="text-slate-600 dark:text-slate-400 mt-2 text-lg">{label}</p>
        </div>
    );
};

const Stats: React.FC = () => {
    const statsData = [
        { value: 600, label: 'People Trained', suffix: '+' },
        { value: 35, label: 'Websites Delivered', suffix: '+' },
        { value: 21, label: 'Automations Done', suffix: '+' },
    ];

    const ref = useRef<HTMLDivElement>(null);
    const [inView, setInView] = useState(false);

    useEffect(() => {
        const observer = new IntersectionObserver(([entry]) => {
            if (entry.isIntersecting) {
                setInView(true);
                observer.unobserve(entry.target);
            }
        }, { threshold: 0.1 });
        
        const currentRef = ref.current;
        if (currentRef) {
            observer.observe(currentRef);
        }

        return () => {
            if(currentRef) observer.unobserve(currentRef);
        }
    }, []);

    return (
         <div ref={ref} className="bg-white dark:bg-slate-800 rounded-3xl shadow-lg border border-slate-200/80 dark:border-slate-700/80 p-8 sm:p-10">
            <div className="grid grid-cols-1 md:grid-cols-3 gap-8">
                {statsData.map(stat => (
                     <StatItem key={stat.label} value={stat.value} label={stat.label} suffix={stat.suffix} inView={inView} />
                ))}
            </div>
        </div>
    )
};

// --- MAIN HOMEPAGE COMPONENT --- //

const slides: Slide[] = [
    {
        title: 'AI Training',
        subtitle: 'Unlock Your Potential with AI',
        description: 'Dazu AI Hub provides expert-led training to empower you and your business in navigating the future of technology.',
        image: 'https://images.unsplash.com/photo-1522202176988-66273c2fd55f?q=80&w=1471&auto=format&fit=crop',
        overlayText: '< WHAT IS AI, AND HOW DOES IT WORK > ?',
        illustration: (
            <svg viewBox="0 0 200 200" className="w-full h-full">
                <defs>
                    <linearGradient id="g1" x1="0%" y1="0%" x2="100%" y2="100%">
                        <stop offset="0%" stopColor="var(--gradient-start)" stopOpacity="0.5" />
                        <stop offset="100%" stopColor="var(--gradient-end)" stopOpacity="0.5" />
                    </linearGradient>
                </defs>
                <path d="M 50,20 Q 150,20 150,120 T 50,180 Q 20,100 50,20 Z" fill="url(#g1)" />
                <circle cx="70" cy="60" r="5" fill="var(--gradient-start)" className="animate-pulse" />
                <circle cx="130" cy="140" r="8" fill="var(--gradient-end)" className="animate-pulse [animation-delay:-0.2s]" />
                <circle cx="100" cy="90" r="3" fill="white" className="animate-pulse [animation-delay:-0.4s]" />
            </svg>
        )
    },
    {
        title: 'Websites & Apps',
        subtitle: 'Modern Websites & Apps, Powered by AI',
        description: 'We build fast, responsive, and AI-integrated websites and apps that captivate your audience and drive business growth.',
        image: 'https://images.unsplash.com/photo-1559028012-481c04fa702d?q=80&w=1336&auto=format&fit=crop',
        illustration: (
            <svg viewBox="0 0 200 200" className="w-full h-full">
                <defs>
                    <linearGradient id="g2" x1="100%" y1="0%" x2="0%" y2="100%">
                        <stop offset="0%" stopColor="var(--gradient-start)" stopOpacity="0.5" />
                        <stop offset="100%" stopColor="var(--gradient-end)" stopOpacity="0.5" />
                    </linearGradient>
                </defs>
                <rect x="30" y="30" width="140" height="140" rx="20" fill="url(#g2)" />
                <line x1="50" y1="60" x2="150" y2="60" stroke="white" strokeWidth="4" className="animate-pulse [animation-delay:-0.1s]" />
                <line x1="50" y1="80" x2="120" y2="80" stroke="var(--gradient-start)" strokeWidth="4" className="animate-pulse [animation-delay:-0.3s]" />
                <line x1="50" y1="100" x2="150" y2="100" stroke="white" strokeWidth="4" className="animate-pulse [animation-delay:-0.5s]" />
                <line x1="50" y1="120" x2="100" y2="120" stroke="var(--gradient-end)" strokeWidth="4" className="animate-pulse" />
            </svg>
        )
    },
    {
        title: 'Automations',
        subtitle: 'Intelligent Automations for Your Business',
        description: 'Streamline workflows and boost productivity with bespoke AI-powered automation solutions and intelligent agents.',
        image: 'https://images.unsplash.com/photo-1526628953301-3e589a6a8b74?q=80&w=1406&auto=format&fit=crop',
        illustration: (
            <svg viewBox="0 0 200 200" className="w-full h-full">
                <defs>
                    <linearGradient id="g3" x1="50%" y1="0%" x2="50%" y2="100%">
                        <stop offset="0%" stopColor="var(--gradient-start)" stopOpacity="0.5" />
                        <stop offset="100%" stopColor="var(--gradient-end)" stopOpacity="0.5" />
                    </linearGradient>
                </defs>
                <path d="M 100,20 L 180,100 L 100,180 L 20,100 Z" fill="url(#g3)" />
                <g className="animate-spin" style={{transformOrigin: '100px 100px', animationDuration: '10s'}}>
                    <circle cx="100" cy="40" r="6" fill="var(--gradient-start)" />
                    <circle cx="160" cy="100" r="6" fill="white" />
                    <circle cx="100" cy="160" r="6" fill="var(--gradient-end)" />
                    <circle cx="40" cy="100" r="6" fill="white" />
                </g>
            </svg>
        )
    },
];

const Homepage: React.FC<{ onGetStartedClick: (courseName?: string, courseId?: number) => void }> = ({ onGetStartedClick }) => {
    const [activeSlide, setActiveSlide] = useState(0);
    const [featuredCourses, setFeaturedCourses] = useState<FeaturedCourse[]>([]);
    
    const coreServices = [
        { image: 'https://images.unsplash.com/photo-1498050108023-c5249f4df085?q=80&w=1472&auto=format&fit=crop', title: 'Modern Websites and Apps Development', description: 'We build fast, responsive, and AI-integrated websites and apps that captivate your audience and drive business growth.'},
        { image: 'https://images.unsplash.com/photo-1593349480503-68551a1c9929?q=80&w=1470&auto=format&fit=crop', title: 'Automations & Agents', description: 'Streamline workflows and boost productivity with bespoke AI-powered automation solutions and intelligent agents.'},
        { image: 'https://images.unsplash.com/photo-1522071820081-009f0129c71c?q=80&w=1470&auto=format&fit=crop', title: 'Expert AI Consulting', description: 'Get strategic guidance on implementing AI in your business to gain a competitive edge and innovate.'}
    ];

    useEffect(() => {
        api.courses()
            .then((r) => {
                setFeaturedCourses(
                    r.data
                        .filter((c: Course) => c.is_active)
                        .slice(0, 4)
                        .map((c: Course) => ({
                            id: c.id,
                            name: c.title,
                            description: c.overview,
                            image: c.cover_image || stock[c.abbreviation] || stock.AI,
                            price: c.course_fee,
                            duration: `${c.duration_months} months`,
                        })),
                );
            })
            .catch(() => setFeaturedCourses([]));
    }, []);

    useEffect(() => {
        const timer = setInterval(() => {
            setActiveSlide(prev => (prev + 1) % slides.length);
        }, 5000); // Change slide every 5 seconds
        return () => clearInterval(timer);
    }, []);

    const currentSlide = slides[activeSlide];

    return (
        <div className="space-y-16">
            {/* Hero Section */}
            <div className="flex flex-col">
                <div className="grid grid-cols-1 lg:grid-cols-2 gap-8 items-center flex-grow">
                    <div className="text-center lg:text-left">
                         <div key={activeSlide} className="animate-fadeIn">
                            <h1 className="text-3xl sm:text-4xl md:text-5xl font-extrabold text-slate-900 dark:text-slate-100 leading-tight mb-4">
                               {currentSlide.subtitle}
                            </h1>
                            <p className="text-lg text-slate-600 dark:text-slate-300 max-w-xl mx-auto lg:mx-0 mb-8">
                               {currentSlide.description}
                            </p>
                            <button
                                onClick={() => onGetStartedClick()}
                                className="text-white font-bold px-8 py-4 rounded-2xl hover:opacity-90 transition-opacity duration-300 shadow-lg text-lg transform hover:scale-105"
                                style={{ background: 'var(--gradient)' }}
                            >
                                Get Started Today
                            </button>
                        </div>
                    </div>
                    <div className="relative w-full h-64 sm:h-80 lg:h-full rounded-2xl overflow-hidden shadow-inner bg-slate-100 dark:bg-gray-800 glowing-border-effect" aria-live="polite">
                         {slides.map((slide, index) => (
                            <div
                                key={index}
                                role="tabpanel"
                                id={`hero-slide-panel-${index}`}
                                aria-labelledby={`hero-slide-tab-${index}`}
                                className={`absolute inset-0 transition-opacity duration-1000 ${index === activeSlide ? 'opacity-100' : 'opacity-0'}`}
                                aria-hidden={index !== activeSlide}
                            >
                                <img src={slide.image} alt={slide.subtitle} className="w-full h-full object-cover" />
                                <div className="absolute inset-0 bg-black/20">
                                    {slide.illustration}
                                </div>
                                {slide.overlayText && (
                                    <div className="absolute inset-0 flex items-center justify-center p-4">
                                        <div className="bg-black/60 p-4 px-6 rounded-lg shadow-2xl backdrop-blur-sm border border-emerald-400/20">
                                            <p className="font-mono text-center text-lg md:text-xl text-emerald-400" style={{textShadow: '0 0 8px rgba(52, 211, 153, 0.7)'}}>
                                                {slide.overlayText}
                                            </p>
                                        </div>
                                    </div>
                                )}
                            </div>
                        ))}
                    </div>
                </div>
                 <div className="flex justify-center lg:justify-start gap-2 sm:gap-4 mt-8" role="tablist" aria-label="Hero content">
                    {slides.map((slide, index) => (
                        <button
                            key={index}
                            role="tab"
                            id={`hero-slide-tab-${index}`}
                            aria-controls={`hero-slide-panel-${index}`}
                            onClick={() => setActiveSlide(index)}
                            aria-selected={index === activeSlide}
                            tabIndex={index === activeSlide ? 0 : -1}
                            className="flex-1 max-w-xs p-3 rounded-lg text-left transition-all duration-300"
                            style={{ background: index === activeSlide ? 'var(--gradient)' : 'var(--background-light)' }}
                        >
                            <span className={`font-bold text-sm ${index === activeSlide ? 'text-white' : 'text-slate-800 dark:text-slate-200'}`}>{slide.title}</span>
                             <div className="w-full bg-slate-300/50 dark:bg-slate-700 rounded-full h-1 mt-2">
                                <div
                                    className="bg-white dark:bg-slate-200 h-1 rounded-full"
                                    style={{ width: index === activeSlide ? '100%' : '0%', transition: index === activeSlide ? 'width 5s linear' : 'width 0s' }}
                                ></div>
                            </div>
                        </button>
                    ))}
                </div>
            </div>
            
            {/* Stats Section */}
            <Stats />

            {/* Featured Courses */}
            <div>
                <div className="text-center mb-10">
                    <h2 className="text-3xl sm:text-4xl font-extrabold text-slate-900 dark:text-slate-100">Featured Courses</h2>
                    <p className="mt-2 text-lg text-slate-600 dark:text-slate-400 max-w-2xl mx-auto">Get started with our most popular training programs.</p>
                </div>
                <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-8">
                    {featuredCourses.length === 0 ? (
                        <p className="col-span-full text-center text-slate-500">Loading courses…</p>
                    ) : (
                        featuredCourses.map((course) => (
                            <FeaturedCourseCard key={course.id} course={course} onSelect={onGetStartedClick} />
                        ))
                    )}
                </div>
            </div>

            {/* Core Services */}
            <div>
                 <div className="text-center mb-10">
                    <h2 className="text-3xl sm:text-4xl font-extrabold text-slate-900 dark:text-slate-100">Our Core Services</h2>
                    <p className="mt-2 text-lg text-slate-600 dark:text-slate-400 max-w-2xl mx-auto">Innovative solutions to power your digital presence.</p>
                </div>
                <div className="grid grid-cols-1 md:grid-cols-3 gap-8">
                    {coreServices.map(service => <ServiceCard key={service.title} {...service} />)}
                </div>
            </div>

            {/* Why Choose Us */}
            <WhyChooseUs />

            {/* CTA */}
            <CTA onGetStartedClick={() => onGetStartedClick()} />

            {/* Footer */}
            <Footer />
        </div>
    );
};

export default Homepage;