import { memo } from 'react';
import PropTypes from 'prop-types';

const LoadingSpinner = memo(({ size = 'md', text = 'Loading...' }) => {
  const sizeClasses = {
    sm: 'h-4 w-4',
    md: 'h-8 w-8',
    lg: 'h-12 w-12',
  };

  return (
    <div className="flex flex-col items-center justify-center py-8">
      <div
        className={`${sizeClasses[size]} animate-spin rounded-full border-4 border-primary-600 border-t-transparent`}
      />
      {text && <p className="mt-3 text-gray-600">{text}</p>}
    </div>
  );
});

LoadingSpinner.displayName = 'LoadingSpinner';

LoadingSpinner.propTypes = {
  size: PropTypes.oneOf(['sm', 'md', 'lg']),
  text: PropTypes.string,
};

export default LoadingSpinner;
