import { AbsoluteFill, Img, interpolate, useCurrentFrame, spring, useVideoConfig, Sequence, staticFile } from "remotion";

interface AppShowcaseProps {
  primaryColor: string;
  secondaryColor: string;
}

interface BlurRegion {
  x: number;
  y: number;
  width: number;
  height: number;
}

interface ScreenConfig {
  src: ReturnType<typeof staticFile>;
  title: string;
  description: string;
  blurRegions?: BlurRegion[];
}

const screens: ScreenConfig[] = [
  {
    src: staticFile("screenshots/01-dashboard.png"),
    title: "Dashboard",
    description: "Real-time patient flow & revenue tracking",
    blurRegions: [{ x: 0, y: 3, width: 18, height: 5 }],
  },
  {
    src: staticFile("screenshots/02-checkin.png"),
    title: "Patient Check-in",
    description: "Quick registration & queue management",
    blurRegions: [
      { x: 0, y: 3, width: 18, height: 5 },
      { x: 55, y: 55, width: 32, height: 42 },
    ],
  },
  {
    src: staticFile("screenshots/vitals-modal.png"),
    title: "Vitals Recording",
    description: "BP, temperature, pulse & SpO₂ tracking",
    blurRegions: [
      { x: 0, y: 3, width: 18, height: 5 },
      { x: 28, y: 16, width: 40, height: 4 },
    ],
  },
  {
    src: staticFile("screenshots/consultation-notes.png"),
    title: "Consultation",
    description: "SOAP notes, diagnoses & prescriptions",
    blurRegions: [
      { x: 0, y: 3, width: 18, height: 5 },
      { x: 17, y: 9, width: 12, height: 5 },
      { x: 28, y: 6, width: 25, height: 3 },
      { x: 52, y: 9, width: 12, height: 3 },
      { x: 22, y: 48, width: 50, height: 8 },
    ],
  },
  {
    src: staticFile("screenshots/06-wards.png"),
    title: "Ward Management",
    description: "Bed allocation & patient tracking",
    blurRegions: [{ x: 0, y: 3, width: 18, height: 5 }],
  },
  {
    src: staticFile("screenshots/05-pharmacy.png"),
    title: "Pharmacy",
    description: "Drug inventory & FIFO dispensing",
    blurRegions: [{ x: 0, y: 3, width: 18, height: 5 }],
  },
  {
    src: staticFile("screenshots/08-laboratory.png"),
    title: "Laboratory",
    description: "Test ordering & result management",
    blurRegions: [{ x: 0, y: 3, width: 18, height: 5 }],
  },
  {
    src: staticFile("screenshots/09-insurance-claims.png"),
    title: "Insurance Claims",
    description: "Claims vetting & NHIS integration",
    blurRegions: [
      { x: 0, y: 3, width: 18, height: 5 },
      { x: 32, y: 54, width: 18, height: 42 },
      { x: 50, y: 54, width: 8, height: 42 },
    ],
  },
];

const BlurOverlay: React.FC<{ regions: BlurRegion[] }> = ({ regions }) => {
  return (
    <>
      {regions.map((region, i) => (
        <div
          key={i}
          style={{
            position: "absolute",
            left: `${region.x}%`,
            top: `${region.y}%`,
            width: `${region.width}%`,
            height: `${region.height}%`,
            backdropFilter: "blur(12px)",
            WebkitBackdropFilter: "blur(12px)",
            background: "rgba(255, 255, 255, 0.1)",
            borderRadius: 4,
          }}
        />
      ))}
    </>
  );
};

const ScreenShowcase: React.FC<{
  screen: ScreenConfig;
  index: number;
  primaryColor: string;
}> = ({ screen, index, primaryColor }) => {
  const frame = useCurrentFrame();
  const { fps } = useVideoConfig();

  const slideIn = spring({
    frame,
    fps,
    config: { damping: 15, stiffness: 60 },
  });

  const translateY = interpolate(slideIn, [0, 1], [100, 0]);
  const opacity = interpolate(slideIn, [0, 1], [0, 1]);

  const zoomProgress = interpolate(frame, [0, 90], [1, 1.05], {
    extrapolateRight: "clamp",
  });

  const titleOpacity = interpolate(frame, [10, 25], [0, 1], {
    extrapolateRight: "clamp",
  });
  const titleY = interpolate(frame, [10, 25], [30, 0], {
    extrapolateRight: "clamp",
  });

  const fadeOut = interpolate(frame, [80, 95], [1, 0], {
    extrapolateLeft: "clamp",
    extrapolateRight: "clamp",
  });

  const glowPulse = 0.4 + Math.sin(frame * 0.1) * 0.2;

  return (
    <AbsoluteFill
      style={{
        background: `linear-gradient(180deg, #0a0f1a 0%, #1a2332 50%, #0d1420 100%)`,
        opacity: fadeOut,
        padding: "20px 40px",
      }}
    >
      {/* Background glow */}
      <div
        style={{
          position: "absolute",
          top: "20%",
          left: "50%",
          transform: "translateX(-50%)",
          width: 500,
          height: 500,
          background: `radial-gradient(circle, ${primaryColor}20 0%, transparent 70%)`,
          borderRadius: "50%",
          filter: "blur(80px)",
        }}
      />

      <div
        style={{
          display: "flex",
          flexDirection: "column",
          height: "100%",
          justifyContent: "center",
          alignItems: "center",
          gap: 20,
        }}
      >
        {/* Title Section */}
        <div
          style={{
            textAlign: "center",
            opacity: titleOpacity,
            transform: `translateY(${titleY}px)`,
            zIndex: 10,
          }}
        >
          {/* Module badge */}
          <div
            style={{
              display: "inline-flex",
              alignItems: "center",
              gap: 8,
              background: `linear-gradient(135deg, ${primaryColor}25, ${primaryColor}10)`,
              border: `2px solid ${primaryColor}50`,
              borderRadius: 20,
              padding: "8px 16px",
              marginBottom: 10,
            }}
          >
            <div
              style={{
                width: 8,
                height: 8,
                borderRadius: "50%",
                background: primaryColor,
                boxShadow: `0 0 15px ${primaryColor}`,
              }}
            />
            <span
              style={{
                fontSize: 16,
                fontWeight: 700,
                color: primaryColor,
                textTransform: "uppercase",
                letterSpacing: 3,
              }}
            >
              {index + 1} / {screens.length}
            </span>
          </div>

          {/* Title */}
          <div
            style={{
              fontSize: 52,
              fontWeight: 800,
              color: "white",
              marginBottom: 8,
              textShadow: "0 4px 30px rgba(0,0,0,0.4)",
            }}
          >
            {screen.title}
          </div>

          {/* Description */}
          <div
            style={{
              fontSize: 26,
              color: "#94a3b8",
              lineHeight: 1.3,
            }}
          >
            {screen.description}
          </div>
        </div>

        {/* Screenshot */}
        <div
          style={{
            display: "flex",
            justifyContent: "center",
            alignItems: "center",
            opacity,
            transform: `translateY(${translateY}px)`,
          }}
        >
          <div
            style={{
              position: "relative",
              width: "100%",
              maxWidth: 950,
              borderRadius: 20,
              overflow: "hidden",
              boxShadow: `
                0 30px 100px rgba(0, 0, 0, 0.7),
                0 0 80px ${primaryColor}${Math.round(glowPulse * 60).toString(16).padStart(2, '0')},
                inset 0 1px 0 rgba(255,255,255,0.1)
              `,
              border: `2px solid rgba(255,255,255,0.1)`,
            }}
          >
            {/* Browser chrome */}
            <div
              style={{
                background: "linear-gradient(180deg, #2a3441 0%, #1e2836 100%)",
                padding: "16px 20px",
                display: "flex",
                alignItems: "center",
                gap: 12,
                borderBottom: "1px solid rgba(255,255,255,0.05)",
              }}
            >
              <div style={{ display: "flex", gap: 10 }}>
                <div style={{ width: 14, height: 14, borderRadius: "50%", background: "#ff5f57" }} />
                <div style={{ width: 14, height: 14, borderRadius: "50%", background: "#febc2e" }} />
                <div style={{ width: 14, height: 14, borderRadius: "50%", background: "#28c840" }} />
              </div>
              <div
                style={{
                  flex: 1,
                  marginLeft: 16,
                  background: "#0f1419",
                  borderRadius: 10,
                  padding: "10px 16px",
                  display: "flex",
                  alignItems: "center",
                  gap: 10,
                }}
              >
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                  <path
                    d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"
                    stroke="#22c55e"
                    strokeWidth="2"
                    fill="none"
                  />
                </svg>
                <span style={{ fontSize: 16, color: "#64748b" }}>
                  healix-hms.com
                </span>
              </div>
            </div>

            {/* Screenshot */}
            <div style={{ overflow: "hidden", position: "relative" }}>
              <Img
                src={screen.src}
                style={{
                  width: "100%",
                  transform: `scale(${zoomProgress})`,
                  transformOrigin: "top center",
                }}
              />
              {screen.blurRegions && <BlurOverlay regions={screen.blurRegions} />}
            </div>
          </div>
        </div>

        {/* Progress dots - Bottom */}
        <div
          style={{
            display: "flex",
            justifyContent: "center",
            gap: 12,
            paddingBottom: 20,
          }}
        >
          {screens.map((_, i) => (
            <div
              key={i}
              style={{
                width: i === index ? 40 : 12,
                height: 12,
                borderRadius: 6,
                background: i === index 
                  ? `linear-gradient(90deg, ${primaryColor}, #3b82f6)` 
                  : "rgba(255,255,255,0.2)",
                boxShadow: i === index ? `0 0 20px ${primaryColor}60` : "none",
              }}
            />
          ))}
        </div>
      </div>
    </AbsoluteFill>
  );
};

export const AppShowcase: React.FC<AppShowcaseProps> = ({ primaryColor }) => {
  const SCREEN_DURATION = 100;

  return (
    <AbsoluteFill>
      {screens.map((screen, index) => (
        <Sequence key={index} from={index * SCREEN_DURATION} durationInFrames={SCREEN_DURATION}>
          <ScreenShowcase screen={screen} index={index} primaryColor={primaryColor} />
        </Sequence>
      ))}
    </AbsoluteFill>
  );
};
