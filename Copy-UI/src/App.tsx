/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import React from 'react';
import Navbar from './components/Navbar';
import HeroSection from './components/HeroSection';
import StatsSection from './components/StatsSection';
import CoursesSection from './components/CoursesSection';
import FeaturesSection from './components/FeaturesSection';
import TestimonialSection from './components/TestimonialSection';
import BlogsSection from './components/BlogsSection';
import PartnersMarquee from './components/PartnersMarquee';
import Footer from './components/Footer';

export default function App() {
  return (
    <div className="min-h-screen bg-slate-50 font-sans overflow-x-hidden">
      <Navbar />
      <main>
        <HeroSection />
        <StatsSection />
        <CoursesSection />
        <FeaturesSection />
        <TestimonialSection />
        <BlogsSection />
        <PartnersMarquee />
      </main>
      <Footer />
    </div>
  );
}
