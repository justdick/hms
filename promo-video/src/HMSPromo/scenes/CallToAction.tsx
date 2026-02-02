import { AbsoluteFill, interpolate, useCurrentFrame, spring, useVideoConfig } from "remotion";

interface CallToActionProps {
  primaryColor: string;
  secondaryColor: string;
  contactPhone: string;
}

export const CallToAction: React.FC<CallToActionProps> = ({
  primaryColor,
  secondaryColor,
  contactPhone,
}) => {
  const frame = useCurrentFrame();
  const { fps } = useVideoConfig();

  const mainScale = spring({
    frame,
    fps,
    config: { damping: 15, stiffness: 100 },
  });

  const buttonPulse = 1 + Math.sin(frame * 0.12) * 0.03;
  const buttonGlow = 0.5 + Math.sin(frame * 0.1) * 0.3;

  const contactOpacity = interpolate(frame, [30, 50], [0, 1], {
    extrapolateRight: "clamp",
  });

  const contactY = interpolate(frame, [30, 50], [40, 0], {
    extrapolateRight: "clamp",
  });

  const logoOpacity = interpolate(frame, [50, 70], [0, 1], {
    extrapolateRight: "clamp",
  });

  // Animated gradient
  const gradientAngle = 180 + Math.sin(frame * 0.02) * 15;

  // Floating particles
  const particles = Array.from({ length: 25 }, (_, i) => ({
    x: (i * 89) % 100,
    y: (i * 67) % 100,
    size: 3 + (i % 4),
    speed: 0.3 + (i % 5) * 0.15,
    delay: i * 2,
  }));

  return (
    <AbsoluteFill
      style={{
        background: `
          radial-gradient(ellipse at 50% 30%, ${primaryColor}30 0%, transparent 50%),
          radial-gradient(ellipse at 50% 70%, ${secondaryColor}20 0%, transparent 50%),
          linear-gradient(${gradientAngle}deg, #0a0f1a 0%, #111827 50%, #0a0f1a 100%)
        `,
        justifyContent: "center",
        alignItems: "center",
        overflow: "hidden",
        padding: "80px 50px",
      }}
    >
      {/* Floating particles */}
      {particles.map((particle, i) => {
        const particleY = interpolate(
          (frame + particle.delay) * particle.speed,
          [0, 100],
          [110, -10],
          { extrapolateRight: "extend" }
        ) % 120;

        return (
          <div
            key={i}
            style={{
              position: "absolute",
              left: `${particle.x}%`,
              top: `${particleY}%`,
              width: particle.size,
              height: particle.size,
              background: i % 2 === 0 ? primaryColor : secondaryColor,
              borderRadius: "50%",
              opacity: 0.4,
              boxShadow: `0 0 ${particle.size * 3}px ${i % 2 === 0 ? primaryColor : secondaryColor}`,
            }}
          />
        );
      })}

      <div
        style={{
          display: "flex",
          flexDirection: "column",
          alignItems: "center",
          gap: 50,
          transform: `scale(${mainScale})`,
          zIndex: 10,
        }}
      >
        {/* Main headline */}
        <div
          style={{
            fontSize: 64,
            fontWeight: 800,
            color: "white",
            textAlign: "center",
            lineHeight: 1.2,
            textShadow: "0 4px 40px rgba(0,0,0,0.4)",
          }}
        >
          Ready to
          <br />
          Transform
          <br />
          <span
            style={{
              background: `linear-gradient(90deg, ${primaryColor}, ${secondaryColor})`,
              WebkitBackgroundClip: "text",
              WebkitTextFillColor: "transparent",
              filter: `drop-shadow(0 0 30px ${primaryColor}50)`,
            }}
          >
            Your Hospital?
          </span>
        </div>

        {/* CTA Button */}
        <div
          style={{
            position: "relative",
            transform: `scale(${buttonPulse})`,
          }}
        >
          {/* Button glow */}
          <div
            style={{
              position: "absolute",
              inset: -6,
              background: `linear-gradient(135deg, ${primaryColor}, ${secondaryColor})`,
              borderRadius: 28,
              filter: `blur(25px)`,
              opacity: buttonGlow,
            }}
          />
          <div
            style={{
              position: "relative",
              background: `linear-gradient(135deg, ${primaryColor}, ${secondaryColor})`,
              padding: "32px 60px",
              borderRadius: 24,
              fontSize: 36,
              fontWeight: 700,
              color: "white",
              boxShadow: `
                0 10px 40px rgba(0, 0, 0, 0.3),
                inset 0 2px 0 rgba(255, 255, 255, 0.2)
              `,
              display: "flex",
              alignItems: "center",
              gap: 16,
            }}
          >
            <span>Request a Demo</span>
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none">
              <path
                d="M5 12H19M19 12L12 5M19 12L12 19"
                stroke="white"
                strokeWidth="2.5"
                strokeLinecap="round"
                strokeLinejoin="round"
              />
            </svg>
          </div>
        </div>

        {/* Phone number - Large and prominent */}
        <div
          style={{
            display: "flex",
            flexDirection: "column",
            alignItems: "center",
            gap: 20,
            marginTop: 30,
            opacity: contactOpacity,
            transform: `translateY(${contactY}px)`,
          }}
        >
          <div
            style={{
              fontSize: 28,
              fontWeight: 600,
              color: "#94a3b8",
              textTransform: "uppercase",
              letterSpacing: 4,
            }}
          >
            Call Us Now
          </div>
          <div
            style={{
              display: "flex",
              alignItems: "center",
              gap: 24,
              background: "rgba(255, 255, 255, 0.1)",
              border: `3px solid ${primaryColor}50`,
              borderRadius: 28,
              padding: "36px 50px",
              boxShadow: `0 0 80px ${primaryColor}30`,
            }}
          >
            <svg width="56" height="56" viewBox="0 0 24 24" fill="none">
              <path
                d="M3 5C3 3.89543 3.89543 3 5 3H8.27924C8.70967 3 9.09181 3.27543 9.22792 3.68377L10.7257 8.17721C10.8831 8.64932 10.6694 9.16531 10.2243 9.38787L7.96701 10.5165C9.06925 12.9612 11.0388 14.9308 13.4835 16.033L14.6121 13.7757C14.8347 13.3306 15.3507 13.1169 15.8228 13.2743L20.3162 14.7721C20.7246 14.9082 21 15.2903 21 15.7208V19C21 20.1046 20.1046 21 19 21H18C9.71573 21 3 14.2843 3 6V5Z"
                stroke={primaryColor}
                strokeWidth="2.5"
                strokeLinecap="round"
                strokeLinejoin="round"
              />
            </svg>
            <span style={{ fontSize: 52, color: "white", fontWeight: 800, letterSpacing: 2 }}>
              {contactPhone}
            </span>
          </div>
        </div>
      </div>

      {/* Bottom logo - Healix HMS branding */}
      <div
        style={{
          position: "absolute",
          bottom: 100,
          display: "flex",
          flexDirection: "column",
          alignItems: "center",
          gap: 20,
          opacity: logoOpacity,
        }}
      >
        <div
          style={{
            width: 80,
            height: 80,
            background: `linear-gradient(135deg, ${primaryColor}, ${secondaryColor})`,
            borderRadius: 20,
            display: "flex",
            justifyContent: "center",
            alignItems: "center",
            boxShadow: `0 0 50px ${primaryColor}50`,
          }}
        >
          <svg width="44" height="44" viewBox="0 0 24 24" fill="none">
            <path
              d="M19 3H5C3.89 3 3 3.89 3 5V19C3 20.11 3.89 21 5 21H19C20.11 21 21 20.11 21 19V5C21 3.89 20.11 3 19 3ZM18 14H14V18H10V14H6V10H10V6H14V10H18V14Z"
              fill="white"
            />
          </svg>
        </div>
        <div style={{ display: "flex", flexDirection: "column", alignItems: "center" }}>
          <span style={{ color: "white", fontSize: 44, fontWeight: 800, letterSpacing: -1 }}>
            Healix <span style={{ color: primaryColor }}>HMS</span>
          </span>
          <span style={{ color: "#94a3b8", fontSize: 20, fontWeight: 500, marginTop: 4 }}>
            Hospital Management System
          </span>
        </div>
      </div>
    </AbsoluteFill>
  );
};
