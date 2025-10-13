import { memo } from 'react';
import PropTypes from 'prop-types';
import { cloneElement, isValidElement } from 'react';

/**
 * Modern animated section header with icons, gradients, and hover effects
 * Matching the StockDetails aesthetic
 */
const SectionHeader = memo(({ icon, title, subtitle, badge, children }) => {
  return (
    <div className="flex items-center justify-between mb-8">
      <div className="flex items-center gap-4">
        {/* Animated icon */}
        {icon && (
          <div className="relative group">
            <div className="absolute inset-0 bg-gradient-to-br from-indigo-400 to-purple-400 rounded-2xl blur opacity-30 group-hover:opacity-60 transition-opacity"></div>
            <div className="relative bg-gradient-to-br from-indigo-500 to-purple-500 rounded-2xl p-4 transform group-hover:scale-110 transition-transform duration-300 shadow-lg">
              {isValidElement(icon) ? (
                cloneElement(icon, { className: `${icon.props.className || ''} text-white` })
              ) : (
                <span className="text-3xl text-white">{icon}</span>
              )}
            </div>
          </div>
        )}
        
        <div>
          <div className="flex items-center gap-3">
            <h2 className="text-3xl md:text-4xl font-black text-transparent bg-clip-text bg-gradient-to-r from-indigo-600 via-purple-600 to-pink-600">
              {title}
            </h2>
            {badge && (
              <span className="px-3 py-1 bg-gradient-to-r from-indigo-100 to-purple-100 text-indigo-700 text-sm font-bold rounded-full">
                {badge}
              </span>
            )}
          </div>
          {subtitle && (
            <p className="text-gray-600 mt-1 text-sm md:text-base">{subtitle}</p>
          )}
        </div>
      </div>

      {/* Right-side content (e.g., tabs, buttons) */}
      {children && (
        <div>{children}</div>
      )}
    </div>
  );
});

SectionHeader.displayName = 'SectionHeader';

SectionHeader.propTypes = {
  icon: PropTypes.oneOfType([PropTypes.string, PropTypes.element]),
  title: PropTypes.string.isRequired,
  subtitle: PropTypes.string,
  badge: PropTypes.string,
  children: PropTypes.node,
};

export default SectionHeader;
