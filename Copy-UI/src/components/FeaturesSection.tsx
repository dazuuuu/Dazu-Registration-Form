import React from 'react';
import { Code, Award, Users, Clock } from 'lucide-react';

const features = [
  {
    icon: <Code size={36} className="text-green-600" />,
    title: "Hands-on Projects",
    description: "Build real-world projects."
  },
  {
    icon: <Award size={36} className="text-green-600" />,
    title: "Industry Recognized",
    description: "Get certified and boost your career."
  },
  {
    icon: <Users size={36} className="text-green-600" />,
    title: "Expert Mentors",
    description: "Learn from industry professionals."
  },
  {
    icon: <Clock size={36} className="text-green-600" />,
    title: "Flexible Learning",
    description: "Study at your own pace."
  }
];

export default function FeaturesSection() {
  return (
    <section className="py-16 bg-white border-b border-slate-100">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8 text-center">
          {features.map((feature, idx) => (
            <div key={idx} className="flex flex-col items-center p-6 rounded-2xl hover:bg-slate-50 transition-colors">
              <div className="w-20 h-20 bg-green-50 rounded-2xl flex items-center justify-center mb-6 shadow-sm border border-green-100">
                {feature.icon}
              </div>
              <h3 className="text-xl font-extrabold text-slate-900 mb-2">{feature.title}</h3>
              <p className="text-slate-600 font-medium">{feature.description}</p>
            </div>
          ))}
        </div>
      </div>
    </section>
  );
}
