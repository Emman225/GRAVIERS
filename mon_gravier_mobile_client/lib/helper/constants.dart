import 'package:flutter/material.dart';

// Color primaryColor = const Color(0xFFFFFCF7);
Color primaryColor = const Color(0xFFFFF7FC);
const Color blackColor = Color(0xff0B2C3D);
const Color buttonTextColor = Color(0xff1D1D1D);
const Color borderColor = Color(0xFFF0F0F0);
// const Color borderColor = Color(0xFFFFEBC4);
const Color greenColor = Color(0xFF34A853);
const Color redColor = Color(0xFFEF262C);
const Color deepGreenColor = Color(0xFF27AE60);
final Color grayColor = const Color(0xff0B2C3D).withOpacity(.3);
// const Color lightningYellowColor = Color(0xffFFBB38);
const Color iconGreyColor = Color(0xff85959E);
const Color paragraphColor = Color(0xff18587A);
const Color appBgColor = Color(0xffFFFCF7);
const Color cardBgGreyColor = Color(0xffEDF1F3);
const Color textGreyColor = Color(0xff797979);
const Color inputFieldBgColor = Color(0xffFFFCF7);
const Color grayBorderColor = Color(0xffE8E8E8);
//const greenGredient = [lightningYellowColor, lightningYellowColor];

// #duration
const kDuration = Duration(milliseconds: 300);

final _borderRadius = BorderRadius.circular(4);

var inputDecorationTheme = InputDecoration(
  isDense: true,
  contentPadding: const EdgeInsets.symmetric(horizontal: 8, vertical: 8),
  hintStyle: const TextStyle(fontSize: 18, height: 1.667),
  border: OutlineInputBorder(
    borderRadius: _borderRadius,
    borderSide: const BorderSide(color: Colors.white),
  ),
  focusedBorder: OutlineInputBorder(
    borderRadius: _borderRadius,
    borderSide: const BorderSide(color: Colors.white),
  ),
  enabledBorder: OutlineInputBorder(
    borderRadius: _borderRadius,
    borderSide: const BorderSide(color: Colors.white),
  ),
  fillColor: primaryColor,
  filled: true,
  focusColor: primaryColor,
);

final gredientColors = [
  [const Color(0xffF6290C), const Color(0xffC70F16)],
  [const Color(0xff019BFE), const Color(0xff0077C1)],
  [const Color(0xff161632), const Color(0xff3D364E)],
  [const Color(0xffF6290C), const Color(0xffC70F16)],
  [const Color(0xff019BFE), const Color(0xff0077C1)],
  [const Color(0xff161632), const Color(0xff3D364E)],
];

const Color whiteColor = Colors.white;
const Color greyColor = const Color(0xFF808080);
const Color orangeColor = const Color(0xFFFFA500);
const Color scaffoldBgColor = const Color(0xFFF2F4F6);

const double fixPadding = 10.0;

const SizedBox heightSpace = const SizedBox(height: 10.0);

const SizedBox height5Space = const SizedBox(height: 5.0);

const SizedBox height20Space = const SizedBox(height: 20.0);

const SizedBox widthSpace = const SizedBox(width: 10.0);

const SizedBox width5Space = const SizedBox(width: 5.0);

const SizedBox width20Space = const SizedBox(width: 20.0);

const TextStyle appBarTextStyle = const TextStyle(
  fontSize: 18.0,
  fontWeight: FontWeight.bold,
  color: blackColor,
);

const TextStyle appBarWhiteTextStyle = const TextStyle(
  fontSize: 18.0,
  fontWeight: FontWeight.bold,
  color: whiteColor,
);

const TextStyle black12RegularTextStyle = const TextStyle(
  fontSize: 12.0,
  color: blackColor,
);

const TextStyle black14RegularTextStyle = const TextStyle(
  fontSize: 14.0,
  color: blackColor,
);

const TextStyle black14BoldTextStyle = const TextStyle(
  fontSize: 14.0,
  color: blackColor,
  fontWeight: FontWeight.bold,
);

const TextStyle black12MediumTextStyle = const TextStyle(
  fontSize: 12.0,
  color: blackColor,
  fontWeight: FontWeight.w500,
);

const TextStyle black14MediumTextStyle = const TextStyle(
  fontSize: 14.0,
  color: blackColor,
  fontWeight: FontWeight.w500,
);

const TextStyle black16MediumTextStyle = const TextStyle(
  fontSize: 16.0,
  color: blackColor,
  fontWeight: FontWeight.w500,
);

const TextStyle black18MediumTextStyle = const TextStyle(
  fontSize: 18.0,
  color: blackColor,
  fontWeight: FontWeight.w500,
);

const TextStyle black16SemiBoldTextStyle = const TextStyle(
  fontSize: 16.0,
  color: blackColor,
  fontWeight: FontWeight.w600,
);

const TextStyle black16BoldTextStyle = const TextStyle(
  fontSize: 16.0,
  color: blackColor,
  fontWeight: FontWeight.bold,
);

const TextStyle black18BoldTextStyle = const TextStyle(
  fontSize: 18.0,
  color: blackColor,
  fontWeight: FontWeight.bold,
);

const TextStyle white12MediumTextStyle = const TextStyle(
  fontSize: 12.0,
  color: whiteColor,
  fontWeight: FontWeight.w500,
);

const TextStyle white12RegularTextStyle = const TextStyle(
  fontSize: 12.0,
  color: whiteColor,
);

const TextStyle white14MediumTextStyle = const TextStyle(
  fontSize: 14.0,
  color: whiteColor,
  fontWeight: FontWeight.w500,
);

const TextStyle white16MediumTextStyle = const TextStyle(
  fontSize: 16.0,
  color: whiteColor,
  fontWeight: FontWeight.w500,
);

const TextStyle white18MediumTextStyle = const TextStyle(
  fontSize: 18.0,
  color: whiteColor,
  fontWeight: FontWeight.w500,
);

const TextStyle white16BoldTextStyle = const TextStyle(
  fontSize: 16.0,
  color: whiteColor,
  fontWeight: FontWeight.bold,
);

const TextStyle white48MediumTextStyle = const TextStyle(
  fontSize: 48.0,
  color: whiteColor,
  fontWeight: FontWeight.w500,
);

const TextStyle white12SemiBoldTextStyle = const TextStyle(
  fontSize: 12.0,
  color: whiteColor,
  fontWeight: FontWeight.w600,
);

const TextStyle white14BoldTextStyle = const TextStyle(
  fontSize: 14.0,
  color: whiteColor,
  fontWeight: FontWeight.bold,
);

const TextStyle white18BoldTextStyle = const TextStyle(
  fontSize: 18.0,
  color: whiteColor,
  fontWeight: FontWeight.bold,
);

const TextStyle white36BoldTextStyle = const TextStyle(
  fontSize: 36.0,
  color: whiteColor,
  fontWeight: FontWeight.bold,
);

TextStyle primaryColor10RegularTextStyle = TextStyle(
  fontSize: 10.0,
  color: primaryColor,
);

TextStyle primaryColor12RegularTextStyle = TextStyle(
  fontSize: 12.0,
  color: primaryColor,
);

TextStyle primaryColor14RegularTextStyle = TextStyle(
  fontSize: 14.0,
  color: primaryColor,
);

TextStyle primaryColor12MediumTextStyle = TextStyle(
  fontSize: 12.0,
  color: primaryColor,
  fontWeight: FontWeight.w500,
);
TextStyle primaryColor14MediumTextStyle = TextStyle(
  fontSize: 14.0,
  color: primaryColor,
  fontWeight: FontWeight.w500,
);

TextStyle primaryColor16MediumTextStyle = TextStyle(
  fontSize: 16.0,
  color: primaryColor,
  fontWeight: FontWeight.w500,
);

TextStyle primaryColor16BoldTextStyle = TextStyle(
  fontSize: 16.0,
  color: primaryColor,
  fontWeight: FontWeight.bold,
);

TextStyle primaryColor18BoldTextStyle = TextStyle(
  fontSize: 18.0,
  color: primaryColor,
  fontWeight: FontWeight.bold,
);

TextStyle primaryColor22BoldTextStyle = TextStyle(
  fontSize: 22.0,
  color: primaryColor,
  fontWeight: FontWeight.bold,
);

TextStyle grey10RegularTextStyle = TextStyle(
  fontSize: 10.0,
  color: greyColor,
);
TextStyle grey12RegularTextStyle = TextStyle(
  fontSize: 12.0,
  color: greyColor,
);

TextStyle grey14RegularTextStyle = TextStyle(
  fontSize: 14.0,
  color: greyColor,
);

TextStyle grey12MediumTextStyle = TextStyle(
  fontSize: 12.0,
  color: greyColor,
  fontWeight: FontWeight.w500,
);

TextStyle grey12MediumItalicTextStyle = TextStyle(
  fontSize: 14.0,
  color: greyColor,
  fontWeight: FontWeight.w500,
  fontStyle: FontStyle.italic,
);

TextStyle grey14MediumTextStyle = TextStyle(
  fontSize: 14.0,
  color: greyColor,
  fontWeight: FontWeight.w500,
);

TextStyle grey16MediumTextStyle = TextStyle(
  fontSize: 16.0,
  color: greyColor,
  fontWeight: FontWeight.w500,
);

TextStyle grey12BoldTextStyle = TextStyle(
  fontSize: 12.0,
  color: greyColor,
  fontWeight: FontWeight.bold,
);

TextStyle grey14BoldTextStyle = TextStyle(
  fontSize: 14.0,
  color: greyColor,
  fontWeight: FontWeight.bold,
);

TextStyle grey16BoldTextStyle = TextStyle(
  fontSize: 16.0,
  color: greyColor,
  fontWeight: FontWeight.bold,
);

TextStyle grey18BoldTextStyle = TextStyle(
  fontSize: 18.0,
  color: greyColor,
  fontWeight: FontWeight.bold,
);

TextStyle grey20BoldTextStyle = TextStyle(
  fontSize: 20.0,
  color: greyColor,
  fontWeight: FontWeight.bold,
);

TextStyle green14MediumTextStyle = TextStyle(
  fontSize: 14.0,
  color: greenColor,
  fontWeight: FontWeight.w500,
);

TextStyle green18MediumTextStyle = TextStyle(
  fontSize: 18.0,
  color: greenColor,
  fontWeight: FontWeight.w500,
);

TextStyle red14MediumTextStyle = TextStyle(
  fontSize: 14.0,
  color: redColor,
  fontWeight: FontWeight.w500,
);

TextStyle red18MediumTextStyle = TextStyle(
  fontSize: 18.0,
  color: redColor,
  fontWeight: FontWeight.w500,
);

double widthOfScreen(BuildContext context) =>
    MediaQuery.of(context).size.width;

double heightOfScreen(BuildContext context) =>
    MediaQuery.of(context).size.height;

Widget addVerticalSpace(double height) {
  return SizedBox(
    height: height,
  );
}

Widget addHorizontalSpace(double width) {
  return SizedBox(
    width: width,
  );
}

MainAxisAlignment mainStart = MainAxisAlignment.start;
MainAxisAlignment mainCenter = MainAxisAlignment.center;
MainAxisAlignment mainEnd = MainAxisAlignment.end;
MainAxisAlignment mainSpaceBet = MainAxisAlignment.spaceBetween;
MainAxisSize mainMax = MainAxisSize.max;
MainAxisSize mainMin = MainAxisSize.min;

CrossAxisAlignment crossStart = CrossAxisAlignment.start;
CrossAxisAlignment crossCenter = CrossAxisAlignment.center;
CrossAxisAlignment crossEnd = CrossAxisAlignment.end;
CrossAxisAlignment crossStretch = CrossAxisAlignment.stretch;

