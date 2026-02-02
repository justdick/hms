import "./index.css";
import { Composition } from "remotion";
import { HelloWorld, myCompSchema } from "./HelloWorld";
import { Logo, myCompSchema2 } from "./HelloWorld/Logo";
import { HMSPromo, hmsPromoSchema } from "./HMSPromo";

// Each <Composition> is an entry in the sidebar!

export const RemotionRoot: React.FC = () => {
  return (
    <>
      {/* HMS Promotional Video - Main composition */}
      <Composition
        id="HMSPromo"
        component={HMSPromo}
        durationInFrames={1580} // ~53 seconds at 30fps
        fps={30}
        width={1080}
        height={1920}
        schema={hmsPromoSchema}
        defaultProps={{
          primaryColor: "#3b82f6",
          secondaryColor: "#8b5cf6",
          hospitalName: "Healix HMS",
          tagline: "Hospital Management, Simplified",
          contactPhone: "0240245347",
        }}
      />

      <Composition
        id="HelloWorld"
        component={HelloWorld}
        durationInFrames={150}
        fps={30}
        width={1920}
        height={1080}
        schema={myCompSchema}
        defaultProps={{
          titleText: "Welcome to Remotion",
          titleColor: "#000000",
          logoColor1: "#91EAE4",
          logoColor2: "#86A8E7",
        }}
      />

      <Composition
        id="OnlyLogo"
        component={Logo}
        durationInFrames={150}
        fps={30}
        width={1920}
        height={1080}
        schema={myCompSchema2}
        defaultProps={{
          logoColor1: "#91dAE2" as const,
          logoColor2: "#86A8E7" as const,
        }}
      />
    </>
  );
};
