import { AlertTriangle, AlertCircle, Info, X } from 'lucide-react';
import { useState } from 'react';

/**
 * Correction Warning Alert Component
 * 
 * Displays warnings when correction conditions are detected:
 * - Price surge + overbought RSI
 * - Bollinger Band z-score extreme
 * - Volume spike + price spike
 * 
 * Severity levels: HIGH, MEDIUM, LOW
 */
export default function CorrectionWarningAlert({ warning, onDismiss }) {
  const [dismissed, setDismissed] = useState(false);

  if (!warning || !warning.warning || dismissed) {
    return null;
  }

  const handleDismiss = () => {
    setDismissed(true);
    if (onDismiss) {
      onDismiss();
    }
  };

  const severity = warning.severity || 'MEDIUM';
  const reasons = warning.reasons || [];
  const confidence = warning.confidence || 0;
  const details = warning.details || {};

  // Styling based on severity
  const config = {
    HIGH: {
      icon: AlertTriangle,
      bgColor: 'bg-red-50',
      borderColor: 'border-red-500',
      textColor: 'text-red-900',
      iconColor: 'text-red-600',
      badgeBg: 'bg-red-500',
      badgeText: 'text-white',
      gradientFrom: 'from-red-500',
      gradientTo: 'to-rose-500',
    },
    MEDIUM: {
      icon: AlertCircle,
      bgColor: 'bg-orange-50',
      borderColor: 'border-orange-500',
      textColor: 'text-orange-900',
      iconColor: 'text-orange-600',
      badgeBg: 'bg-orange-500',
      badgeText: 'text-white',
      gradientFrom: 'from-orange-500',
      gradientTo: 'to-amber-500',
    },
    LOW: {
      icon: Info,
      bgColor: 'bg-yellow-50',
      borderColor: 'border-yellow-500',
      textColor: 'text-yellow-900',
      iconColor: 'text-yellow-600',
      badgeBg: 'bg-yellow-500',
      badgeText: 'text-white',
      gradientFrom: 'from-yellow-500',
      gradientTo: 'to-amber-500',
    },
  };

  const style = config[severity] || config.MEDIUM;
  const Icon = style.icon;

  return (
    <div className="relative group mb-6">
      {/* Animated gradient glow */}
      <div className={`absolute -inset-0.5 bg-gradient-to-r ${style.gradientFrom} ${style.gradientTo} rounded-2xl opacity-20 blur-sm group-hover:opacity-30 transition-opacity animate-pulse`}></div>
      
      {/* Main alert container */}
      <div className={`relative ${style.bgColor} border-2 ${style.borderColor} rounded-2xl p-5 shadow-lg`}>
        {/* Header */}
        <div className="flex items-start justify-between mb-4">
          <div className="flex items-start gap-3 flex-1">
            {/* Icon */}
            <div className={`flex-shrink-0 p-2 rounded-xl bg-white shadow-md`}>
              <Icon className={`w-6 h-6 ${style.iconColor}`} />
            </div>
            
            {/* Title and severity badge */}
            <div className="flex-1">
              <div className="flex items-center gap-2 mb-1">
                <h3 className={`text-lg font-black ${style.textColor}`}>
                  ⚠️ Correction Warning
                </h3>
                <span className={`text-xs px-2 py-1 ${style.badgeBg} ${style.badgeText} rounded-full font-bold`}>
                  {severity}
                </span>
              </div>
              <p className={`text-sm ${style.textColor} opacity-90`}>
                Potential price correction detected based on technical indicators
              </p>
            </div>
          </div>

          {/* Dismiss button */}
          {onDismiss && (
            <button
              onClick={handleDismiss}
              className={`flex-shrink-0 p-1 hover:bg-white rounded-lg transition-colors ${style.textColor}`}
              aria-label="Dismiss alert"
            >
              <X className="w-5 h-5" />
            </button>
          )}
        </div>

        {/* Reasons list */}
        {reasons.length > 0 && (
          <div className="mb-4">
            <h4 className={`text-sm font-bold ${style.textColor} mb-2`}>Triggers:</h4>
            <ul className="space-y-2">
              {reasons.map((reason, index) => (
                <li key={index} className="flex items-start gap-2">
                  <span className={`flex-shrink-0 w-1.5 h-1.5 rounded-full ${style.badgeBg} mt-2`}></span>
                  <span className={`text-sm ${style.textColor} leading-relaxed`}>{reason}</span>
                </li>
              ))}
            </ul>
          </div>
        )}

        {/* Technical details */}
        {Object.keys(details).length > 0 && (
          <div className="grid grid-cols-2 md:grid-cols-3 gap-3 mb-4">
            {details.price_change_7d !== undefined && (
              <DetailCard
                label="7-Day Change"
                value={`${details.price_change_7d > 0 ? '+' : ''}${details.price_change_7d.toFixed(1)}%`}
                style={style}
              />
            )}
            {details.rsi !== undefined && (
              <DetailCard
                label="RSI"
                value={details.rsi.toFixed(0)}
                style={style}
              />
            )}
            {details.volume_ratio !== undefined && (
              <DetailCard
                label="Volume Ratio"
                value={`${details.volume_ratio.toFixed(1)}x`}
                style={style}
              />
            )}
          </div>
        )}

        {/* Confidence indicator */}
        {confidence > 0 && (
          <div className="mb-4">
            <div className="flex items-center justify-between text-xs mb-1">
              <span className={`font-semibold ${style.textColor}`}>Confidence</span>
              <span className={`font-bold ${style.textColor}`}>{(confidence * 100).toFixed(0)}%</span>
            </div>
            <div className="h-2 bg-white rounded-full overflow-hidden">
              <div
                className={`h-full bg-gradient-to-r ${style.gradientFrom} ${style.gradientTo} transition-all duration-500`}
                style={{ width: `${confidence * 100}%` }}
              />
            </div>
          </div>
        )}

        {/* Action recommendation */}
        <div className="p-3 bg-white rounded-lg border border-gray-200">
          <p className="text-sm text-gray-700 leading-relaxed">
            <strong className={style.textColor}>Recommendation:</strong> {' '}
            {severity === 'HIGH' && 'Exercise extreme caution. Consider reducing position size or taking profits. Monitor closely for reversal signals.'}
            {(severity === 'MEDIUM' || severity === 'MODERATE') && 'Watch for potential pullback. Consider setting tighter stop losses and monitoring support levels.'}
            {severity === 'LOW' && 'Be aware of potential consolidation. Current gains may pause as market digests recent moves.'}
            {!['HIGH', 'MEDIUM', 'MODERATE', 'LOW'].includes(severity) && 'Monitor technical indicators and be prepared for potential price movements.'}
          </p>
        </div>
      </div>
    </div>
  );
}

/**
 * Detail Card for displaying technical metrics
 */
function DetailCard({ label, value, style }) {
  return (
    <div className="p-3 bg-white rounded-lg border border-gray-200">
      <div className="text-xs text-gray-600 mb-1">{label}</div>
      <div className={`text-lg font-bold ${style.textColor}`}>{value}</div>
    </div>
  );
}

/**
 * Compact version of correction warning
 */
export function CorrectionWarningBadge({ warning }) {
  if (!warning || !warning.warning) {
    return null;
  }

  const severity = warning.severity || 'MEDIUM';
  
  const colorMap = {
    HIGH: 'bg-red-500 text-white',
    MEDIUM: 'bg-orange-500 text-white',
    LOW: 'bg-yellow-500 text-white',
  };

  return (
    <div className={`inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-bold ${colorMap[severity]} shadow-lg animate-pulse`}>
      <AlertTriangle className="w-3.5 h-3.5" />
      <span>Correction Risk: {severity}</span>
    </div>
  );
}
