import React, { useState } from 'react';
import { useRouter } from '../router';
import { 
  Shield, 
  Target, 
  Lightbulb, 
  Users, 
  Award, 
  GraduationCap, 
  Laptop, 
  BookOpen, 
  MapPin, 
  Plus, 
  Minus 
} from 'lucide-react';

export default function About() {
  const { navigate } = useRouter();
  const [activeFaq, setActiveFaq] = useState<number | null>(null);

  const stats = [
    { label: "Hands-on Labs", value: "100%" },
    { label: "Job Placement Support", value: "85%" },
    { label: "KICD-Aligned Curriculums", value: "Yes" },
    { label: "Thika Campus Rooms", value: "Room 529" }
  ];

  const coreValues = [
    {
      icon: Target,
      title: "Career Alignment",
      desc: "Our courses align closely with technical requirements drafted by software studios, networking firms, and business sectors."
    },
    {
      icon: Lightbulb,
      title: "Lab-First Philosophy",
      desc: "We skip boring theory and jump straight into functional labs where students type code, structure firewalls, and compile models."
    },
    {
      icon: Shield,
      title: "Uncompromising Integrity",
      desc: "Honest mentoring, clear evaluation rubrics, and high accountability for every enrolled student."
    }
  ];

  const trainers = [
    {
      name: "Eng. Davis Kamau",
      role: "Lead Software & Cloud Architect",
      bio: "Ex-Safaricom software engineer with 8+ years experience developing React systems, microservices, and secure databases.",
      initials: "DK"
    },
    {
      name: "Dr. Catherine Wambui",
      role: "AI & Data Science Specialist",
      bio: "Active data consultant specializing in custom LLMs, prompt pipelines, business intelligence, and Power BI reporting.",
      initials: "CW"
    },
    {
      name: "Mark Mwenda",
      role: "Cybersecurity & Cisco Instructor",
      bio: "Certified Security Engineer lecturing ethical hacking, network defenses, router setups, and CompTIA systems.",
      initials: "MM"
    }
  ];

  const faqs = [
    {
      q: "Where is Dazu Hub Institute located physically?",
      a: "We are located at Witeithie House, Room 529, in Thika Town. We encourage prospective students to visit our computer coding labs and discuss learning goals directly with our staff."
    },
    {
      q: "Are the training sessions conducted physically or online?",
      a: "We support both learning modes! We offer intensive in-person physical classes in our well-equipped Thika labs, as well as highly interactive live virtual sessions for students who prefer studying from home."
    },
    {
      q: "Will I receive an industry-recognized certificate?",
      a: "Yes. Upon successful completion of your course and submission of your capstone projects, you will receive a certified certificate of competence from Dazu Hub Institute."
    },
    {
      q: "Do you offer flexible installment options for tuition fees?",
      a: "Yes. Dazu Hub supports friendly payment installments for all courses. You can divide the tuition fees into 2 or 3 manageable monthly payments over your study duration."
    },
    {
      q: "What are the entry prerequisites for the courses?",
      a: "For our beginner tracks (Software Development, Data Analytics, Graphics Design, Social Media), no prior programming experience is required! All you need is basic computer literacy and a hunger to learn."
    }
  ];

  return (
    <div className="bg-white min-h-screen py-16 text-slate-900">
      <div className="max-w-[1280px] mx-auto px-4 md:px-8 lg:px-12">
        
        {/* Intro Hero Section */}
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-16 items-center mb-24">
          <div className="space-y-6">
            <span className="text-green-600 text-xs uppercase tracking-widest font-extrabold px-3 py-1 bg-green-50 border border-green-200 rounded-full w-fit">
              About Dazu Hub
            </span>
            <h1 className="text-4xl md:text-5xl font-extrabold tracking-tight text-slate-900 leading-tight">
              Empowering Next-Gen Innovators in Thika.
            </h1>
            <p className="text-slate-600 font-medium leading-relaxed">
              Dazu Hub Tech Skills Institute is a premier technology training school located in Thika Town. We bridge the critical gap between university theory and high-growth industry standards.
            </p>
            <p className="text-slate-600 font-medium leading-relaxed">
              Our training is designed around physical coding labs, daily software execution, and custom digital graphic sprints. We provide students, professionals, and corporate businesses with the practical expertise needed to innovate in today's tech-driven ecosystem.
            </p>
            <div className="pt-4 flex gap-4">
              <button
                onClick={() => navigate('/apply')}
                className="px-6 py-3.5 bg-green-600 hover:bg-green-700 text-white font-bold text-sm rounded-xl cursor-pointer transition-colors"
              >
                Apply for Admission
              </button>
              <button
                onClick={() => navigate('/courses')}
                className="px-6 py-3.5 bg-white border border-slate-200 text-slate-800 font-bold text-sm rounded-xl hover:bg-slate-50 cursor-pointer transition-colors"
              >
                View Our Courses
              </button>
            </div>
          </div>

          {/* Simple Clean Statistics Block */}
          <div className="grid grid-cols-2 gap-6 p-8 bg-slate-50 border border-slate-200 rounded-2xl">
            {stats.map((stat, idx) => (
              <div key={idx} className="bg-white border border-slate-200 rounded-xl p-6 text-center">
                <span className="block text-3xl md:text-4xl font-extrabold text-green-600">{stat.value}</span>
                <span className="block text-xs font-bold text-slate-500 uppercase tracking-wider mt-2">{stat.label}</span>
              </div>
            ))}
          </div>
        </div>

        {/* Our Mission & Values */}
        <div className="border-t border-slate-100 pt-20 mb-24">
          <div className="max-w-2xl text-left space-y-4 mb-16">
            <span className="text-green-600 text-xs uppercase tracking-widest font-extrabold">Value Pillars</span>
            <h2 className="text-3xl font-extrabold text-slate-900 tracking-tight">Our Core Mission and Values</h2>
            <p className="text-slate-600 font-medium text-sm">
              We operate under three primary philosophies to guarantee value for every single minute spent in our technical lectures.
            </p>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-3 gap-8">
            {coreValues.map((value, idx) => {
              const Icon = value.icon;
              return (
                <div key={idx} className="bg-slate-50 border border-slate-200 rounded-2xl p-8 text-left">
                  <div className="p-3 bg-white border border-slate-200 rounded-xl text-green-600 w-fit mb-6">
                    <Icon className="h-6 w-6" />
                  </div>
                  <h3 className="text-lg font-bold text-slate-900 mb-3">{value.title}</h3>
                  <p className="text-slate-600 text-xs font-semibold leading-relaxed">{value.desc}</p>
                </div>
              );
            })}
          </div>
        </div>

        {/* Expert Trainers Section */}
        <div className="border-t border-slate-100 pt-20 mb-24">
          <div className="max-w-2xl text-left space-y-4 mb-16">
            <span className="text-green-600 text-xs uppercase tracking-widest font-extrabold">Industry Experts</span>
            <h2 className="text-3xl font-extrabold text-slate-900 tracking-tight">Meet Our Lead Technical Trainers</h2>
            <p className="text-slate-600 font-medium text-sm">
              Our lectures are led by professional tech experts actively writing software and structuring digital safety systems.
            </p>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-3 gap-8">
            {trainers.map((trainer, idx) => (
              <div key={idx} className="bg-slate-50 border border-slate-200 rounded-2xl p-8 text-left flex flex-col justify-between h-[300px]">
                <div>
                  <div className="flex items-center gap-4 mb-6">
                    <div className="h-12 w-12 bg-green-600 text-white font-extrabold text-sm rounded-full flex items-center justify-center">
                      {trainer.initials}
                    </div>
                    <div>
                      <h3 className="text-base font-bold text-slate-900">{trainer.name}</h3>
                      <p className="text-xs text-green-600 font-bold tracking-wide mt-0.5">{trainer.role}</p>
                    </div>
                  </div>
                  <p className="text-slate-600 text-xs font-semibold leading-relaxed">
                    {trainer.bio}
                  </p>
                </div>
                <div className="pt-6 border-t border-slate-200/55 flex items-center gap-2 text-[10px] uppercase font-bold text-slate-400 tracking-wider">
                  <Award className="h-4 w-4 text-green-600" />
                  Lead Instructor
                </div>
              </div>
            ))}
          </div>
        </div>

        {/* FAQ Accordion Section */}
        <div className="border-t border-slate-100 pt-20 max-w-4xl mx-auto">
          <div className="text-center space-y-4 mb-16">
            <span className="text-green-600 text-xs uppercase tracking-widest font-extrabold">Got Questions?</span>
            <h2 className="text-3xl font-extrabold text-slate-900 tracking-tight">Frequently Asked Questions</h2>
            <p className="text-slate-600 font-medium text-sm">
              Here are direct, helpful answers to the common questions about Dazu Hub courses, campus, and fees.
            </p>
          </div>

          <div className="space-y-4">
            {faqs.map((faq, idx) => {
              const isOpen = activeFaq === idx;
              return (
                <div 
                  key={idx} 
                  className="border border-slate-200 rounded-2xl bg-slate-50 overflow-hidden transition-colors"
                >
                  <button
                    onClick={() => setActiveFaq(isOpen ? null : idx)}
                    className="w-full flex items-center justify-between p-6 text-left font-bold text-slate-900 text-sm hover:bg-slate-100 cursor-pointer"
                  >
                    <span>{faq.q}</span>
                    {isOpen ? <Minus className="h-4 w-4 text-green-600 shrink-0" /> : <Plus className="h-4 w-4 text-green-600 shrink-0" />}
                  </button>
                  {isOpen && (
                    <div className="p-6 bg-white border-t border-slate-200 text-slate-600 text-xs font-semibold leading-relaxed">
                      {faq.a}
                    </div>
                  )}
                </div>
              );
            })}
          </div>
        </div>

      </div>
    </div>
  );
}
