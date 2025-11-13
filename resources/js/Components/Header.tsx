import React from 'react';

const SensysLogo = () => (
    <svg width="120" height="40" viewBox="0 0 120 40" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M16 0L0 12V36L16 24V0Z" fill="#D1D5DB"/>
        <text x="22" y="27" fontFamily="Arial, sans-serif" fontSize="24" fontWeight="bold" fill="#D1D5DB">
            sensys
        </text>
    </svg>
);


const Header: React.FC = () => {
  return (
    <header className="bg-black bg-opacity-30 backdrop-blur-sm shadow-lg sticky top-0 z-50">
      <div className="container mx-auto px-4 py-3 flex items-center justify-between">
        <div className="flex items-center space-x-3">
            <SensysLogo />
            <span className="text-xl font-light text-gray-400 hidden sm:inline">| Image Studio</span>
        </div>
      </div>
    </header>
  );
};

export default Header;
