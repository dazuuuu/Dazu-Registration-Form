import React, { useState, useEffect } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { ArrowRight, Code, Shield, Database } from 'lucide-react';

const slides = [
  {
    id: 1,
    title: 'Master Web & Software Development',
    subtitle: 'Practical training for the modern tech landscape',
    description: 'Learn Python, Data Analytics, Cybersecurity, and more with our hands-on approach.',
    color: 'bg-green-900',
    icon: <Code size={48} className="text-green-400 mb-6" />,
    image: 'https://images.unsplash.com/photo-1517694712202-14dd9538aa97?q=80&w=2070&auto=format&fit=crop',
  },
  {
    id: 2,
    title: 'Secure Your Future with Cybersecurity',
    subtitle: 'Learn to protect networks and data',
    description: 'Get practical experience in ethical hacking, network defense, and risk management.',
    color: 'bg-slate-900',
    icon: <Shield size={48} className="text-emerald-400 mb-6" />,
    image: 'https://images.unsplash.com/photo-1550751827-4bd374c3f58b?q=80&w=2070&auto=format&fit=crop',
  },
  {
    id: 3,
    title: 'Unlock Insights with Data Analytics',
    subtitle: 'Turn data into actionable intelligence',
    description: 'Master tools and techniques to analyze, visualize, and interpret complex data sets.',
    color: 'bg-emerald-900',
    icon: <Database size={48} className="text-amber-400 mb-6" />,
    image: 'https://images.unsplash.com/photo-1551288049-bebda4e38f71?q=80&w=2070&auto=format&fit=crop',
  }
];

export default function HeroSection() {
  const [currentSlide, setCurrentSlide] = useState(0);

  useEffect(() => {
    const timer = setInterval(() => {
      setCurrentSlide((prev) => (prev + 1) % slides.length);
    }, 5000);
    return () => clearInterval(timer);
  }, []);

  return (
    <div className="relative h-screen w-full overflow-hidden bg-slate-950 text-white">
      <AnimatePresence mode="wait">
        <motion.div
          key={currentSlide}
          initial={{ opacity: 0, scale: 1.05 }}
          animate={{ opacity: 1, scale: 1 }}
          exit={{ opacity: 0 }}
          transition={{ duration: 0.8, ease: "easeInOut" }}
          className="absolute inset-0 z-0"
        >
          <div
            className="absolute inset-0 bg-cover bg-center"
            style={{ backgroundImage: `url(${slides[currentSlide].image})` }}
          />
        </motion.div>
      </AnimatePresence>

      <div className="relative z-10 flex h-full flex-col items-center justify-end pb-32 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto text-center">
        <AnimatePresence mode="wait">
          <motion.div
            key={`content-${currentSlide}`}
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            exit={{ opacity: 0, y: -20 }}
            transition={{ duration: 0.5, delay: 0.2 }}
            className="flex flex-col items-center"
          >
            {slides[currentSlide].icon}
            <h1 className="mb-4 text-3xl font-extrabold tracking-tight sm:text-5xl md:text-6xl lg:text-7xl">
              {slides[currentSlide].title}
            </h1>
            <p className="mb-6 max-w-2xl text-lg font-medium text-slate-300 sm:text-2xl">
              {slides[currentSlide].subtitle}
            </p>
            <p className="mb-8 md:mb-10 max-w-3xl text-sm md:text-lg text-slate-400 px-2 md:px-0">
              {slides[currentSlide].description}
            </p>
            
            <div className="flex flex-col sm:flex-row gap-3 md:gap-4 justify-center w-full sm:w-auto px-4 sm:px-0">
              <a href="/apply/" className="w-full sm:w-auto group flex items-center justify-center rounded-full bg-white px-6 md:px-8 py-3.5 md:py-4 text-base md:text-lg font-semibold text-slate-900 transition-all hover:bg-slate-200">
                Get started
                <ArrowRight className="ml-2 h-5 w-5 transition-transform group-hover:translate-x-1" />
              </a>
              <a href="/courses/" className="w-full sm:w-auto flex items-center justify-center rounded-full border-2 border-white/20 bg-white/10 px-6 md:px-8 py-3.5 md:py-4 text-base md:text-lg font-semibold text-white backdrop-blur-sm transition-all hover:bg-white/20">
                Our courses
              </a>
            </div>
          </motion.div>
        </AnimatePresence>
      </div>

      <div className="absolute bottom-8 left-0 right-0 z-20 flex justify-center gap-3">
        {slides.map((_, index) => (
          <button
            key={index}
            onClick={() => setCurrentSlide(index)}
            className={`h-3 rounded-full transition-all duration-300 ${
              currentSlide === index ? 'w-10 bg-white' : 'w-3 bg-white/40 hover:bg-white/60'
            }`}
            aria-label={`Go to slide ${index + 1}`}
          />
        ))}
      </div>
    </div>
  );
}
